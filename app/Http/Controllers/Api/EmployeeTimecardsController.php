<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeTimecardsController extends Controller
{
    public function getEmployeeTimecards(Request $request)
    {
        // Fetch employee_id from the request
        $employeeId = $request->input('employee_id');
        $entityId = $request->input('id');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');
        $departmentId = $request->input('departmentId');
        $positionId = $request->input('positionId');
    
        // Fetch database name and timezone using a query
        $entityInfo = DB::table('payroll.entities')
            ->select('database_name', 'timezone')
            ->where('id', $entityId)
            ->first();
    
        // Check if the query returned a result
        if ($entityInfo) {
            // Use the result's database_name and timezone as dynamic variables
            $databaseName = $entityInfo->database_name;
            $timezone = $entityInfo->timezone;
        } else {
            // Set default values or handle the situation where no result is found
            $databaseName = 'payroll_ecmci';
            $timezone = 'UTC';
        }
    
        // Fetch employee timecards with details using the dynamic database name
        $query = DB::table("$databaseName.employee_timecards")
            ->join("$databaseName.employee_timecard_details", "$databaseName.employee_timecards.id", '=', "$databaseName.employee_timecard_details.employee_timecard_id")
            ->select(
                "$databaseName.employee_timecards.employee_id",
                "$databaseName.employee_timecards.timecard_date",
                "$databaseName.employee_timecards.id as employee_timecard_id",
                "$databaseName.employee_timecard_details.punch_time",
                "$databaseName.employee_timecard_details.punch_method",
                "$databaseName.employee_timecard_details.employee_shift_id",
                "$databaseName.employee_timecard_details.shift_status",
                "$databaseName.employee_timecard_details.temperature",
                "$databaseName.employee_timecard_details.device_sn",
                "$databaseName.employee_timecard_details.punch_type",
                "$databaseName.employee_timecard_details.id as timecard_details_id",
                "$databaseName.employee_timecard_details.status_code"
            )
            ->where("$databaseName.employee_timecards.employee_id", $employeeId)
            ->whereNull("$databaseName.employee_timecard_details.deleted_at");
    
        // Add condition for start date if provided
        if ($startDate !== null) {
            $query->whereDate("$databaseName.employee_timecards.timecard_date", '>=', $startDate);
        }
    
        // Add condition for end date if provided
        if ($endDate !== null) {
            $query->whereDate("$databaseName.employee_timecards.timecard_date", '<=', $endDate);
        }

        if ($departmentId !== null) {
            $query->where("$databaseName.employee_timecards.department_id", $departmentId);
        }
        
        // Add condition for position id if provided
        if ($positionId !== null) {
            $query->where("$databaseName.employee_timecards.position_id", $positionId);
        }
    
        // Order the results by timecard_date in descending order
        $query->orderByDesc("$databaseName.employee_timecards.timecard_date");
    
        // If start date is not provided, paginate the results
        if ($startDate === null) {
            $perPage = 30;
            $page = $request->input('page', 1);
            $totalItems = $query->count();
            $offset = ($page - 1) * $perPage;
            $query->skip($offset)->take($perPage);
        }
    
        // Retrieve all matching records
        $employeeTimecardsWithDetails = $query->get();
    
        // Organize the data by grouping entries with the same date and punch type
        $groupedTimecards = [];
        foreach ($employeeTimecardsWithDetails as $timecard) {
            $date = $timecard->timecard_date;
            $punchTypeLabel = $this->getPunchTypeLabel($timecard->punch_type);
            $formattedPunchTime = $this->formatPunchTime($timecard->punch_time, $timezone);
            $day = ($timezone) ? date('l', strtotime($timecard->timecard_date . ' ' . $formattedPunchTime . ' ' . $timezone)) : '';
    
            $entry = [
                'employee_id' => $timecard->employee_id,
                'timecard_date' => $timecard->timecard_date,
                'employee_timecard_id' => $timecard->employee_timecard_id,
                'punch_time' => $formattedPunchTime,
                'temperature' => $timecard->temperature,
                'device_sn' => $timecard->device_sn,
                'id' => $timecard->timecard_details_id,
                'punch_type' => $timecard->punch_type,
                'punch_method' => $timecard->punch_method,
                'status_code' => $timecard->status_code,
                'date' => $timecard->timecard_date, // Include the date as a separate field
                'day' => $day,   // Include the day based on the timezone
            ];
    
            if (!isset($groupedTimecards[$date])) {
                $groupedTimecards[$date] = [
                    'punches_array' => [
                        'punch_in' => [],
                        'break_out' => [],
                        'break_in' => [],
                        'punch_out' => [],
                    ],
                ];
            }
    
            // Check if it's a "punch_in" entry and the time is before a certain threshold (e.g., 5 AM)
            $thresholdTime = ($timezone && strpos($timezone, 'Asia') !== false) ? '10:00 AM' : '05:00 AM';
    
            if ($punchTypeLabel === 'punch_in' && strtotime($formattedPunchTime) > strtotime($thresholdTime) && strpos($timezone, 'Asia') !== false) {
                // If yes, associate it with the next day
                $nextDate = date('Y-m-d', strtotime('+1 day', strtotime($date)));
                $groupedTimecards[$nextDate]['punches_array']['punch_in'][] = $entry;
            } else {
                // Otherwise, add the entry to the current day's punches array
                $groupedTimecards[$date]['punches_array'][$punchTypeLabel][] = $entry;
            }
        }
    
        // Pagination
        if ($startDate === null) {
            $lastPage = ceil($totalItems / $perPage);
            $pagination = [
                'total' => $totalItems,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ];
        } else {
            $pagination = null; // If start date is provided, no pagination needed
        }
    
        // Enclose the paginated timecards array in the result data
        $result = [
            'status' => 'success',
            'message' => 'Employee timecards with details fetched successfully',
            'data' => [
                'list' => array_values($groupedTimecards),
                'pagination' => $pagination,
            ],
        ];
    
        return response()->json($result, 200);
    }
    
    
    // Helper function to get punch type label
    private function getPunchTypeLabel($punchType)
    {
        switch ($punchType) {
            case 0:
                return 'punch_in';
            case 2:
                return 'break_out';
            case 4:
                return 'break_in';
            case 5:
                return 'punch_out';
            default:
                return 'unknown';
        }
    }

    // Helper function to format punch time with timezone
    private function formatPunchTime($punchTime, $timezone)
    {
        return Carbon::parse($punchTime)->setTimezone($timezone)->format('g:i A');
    }
}
