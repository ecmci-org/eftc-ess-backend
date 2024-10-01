<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeHireHistoryController extends Controller
{
    public function index($employee_id, $entity_id)
    {
        // Fetch the database name associated with the entity_id
        $entityInfo = DB::table('payroll.entities')
                        ->select('database_name')
                        ->where('id', $entity_id)
                        ->first();

        // Check if the query returned a result
        if (!$entityInfo) {
            return response()->json(['error' => 'Entity not found'], 404);
        }

        // Use the result's database_name
        $databaseName = $entityInfo->database_name;

        // Execute the query to fetch employee hire history from the specified database
        $employeeHireHistory = DB::table("$databaseName.employee_hire_history")
            ->where('employee_id', $employee_id)
            ->get();
        
        // Return the employee hire history as a JSON response
        return response()->json($employeeHireHistory);
    }

    public function getChanges($employee_id, $entity_id, $date)
    {

        $entityInfo = DB::table('payroll.entities')
        ->select('database_name')
        ->where('id', $entity_id)
        ->first();

        // Check if the query returned a result
        if (!$entityInfo) {
        return response()->json(['error' => 'Entity not found'], 404);
        }

        // Use the result's database_name
        $databaseName = $entityInfo->database_name;
        // Execute the query to fetch employee change notices
        $changeNotices = DB::table("$databaseName.ess_employee_change_notice")
        ->where('status', 1)
        ->where('employee_id', $employee_id)
        ->whereRaw('SUBSTRING(created_at, 1, 4) = ?', [$date])
        ->get();

        // Return the employee change notices as JSON response
        return response()->json($changeNotices, 200);
    }
}
