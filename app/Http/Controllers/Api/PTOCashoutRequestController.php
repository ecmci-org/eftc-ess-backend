<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PTOCashoutRequestController extends Controller
{

    public function index(Request $request)
    {
        // Validate the request parameters
        $request->validate([
            'entity_id' => 'required|integer',
            'employee_id' => 'integer',
        ]);
    
        // Fetch the entity_id and employee_id from the request
        $entityId = $request->input('entity_id');
        $employeeId = $request->input('employee_id');
    
        // Fetch the database name associated with the entity_id
        $entityInfo = DB::table('payroll.entities')
                        ->select('database_name')
                        ->where('id', $entityId)
                        ->first();
    
        // Check if the query returned a result
        if (!$entityInfo) {
            return response()->json(['error' => 'Entity not found'], 404);
        }
    
        // Use the result's database_name
        $databaseName = $entityInfo->database_name;
    
        // Query builder instance for "ess_pto_cashout_applications" table
        $query = DB::table($databaseName . '.ess_pto_cashout_applications')
                    ->where('entity_id', $entityId)
                    ->orderByDesc('id'); // Order by id DESC
    
        // Filter the results based on the employee_id if provided
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
    
        // Fetch data from the table
        $applications = $query->get();
    
        // Return the fetched data
        return response()->json($applications, 200);
    }
    
  
public function store(Request $request)
    {
        // Define validation rules
        $rules = [
            'employee_id' => 'required|integer',
            'leave_type' => 'required|integer',
            'hours_requested' => 'required|integer',
            'entity_id' => 'required|integer',
            'first_approver' => 'required|integer',
            'second_approver' => 'required|integer',
        ];

        // Validate the request
        $validator = \Validator::make($request->all(), $rules);

        // Check if validation fails
        if ($validator->fails()) {
            // Return validation error response
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Additional validation to ensure non-string integers
        $fields = ['employee_id', 'leave_type', 'hours_requested', 'entity_id'];
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                return response()->json(['error' => "The $field must be a non-string integer."], 422);
            }
        }

        // Fetch the entity_id from the request
        $entityId = $request->input('entity_id');
        $signature = $request->input('signature');
        $firstApproverId = $request->input('first_approver');
        $secondApproverId = $request->input('second_approver');

        // Fetch the database name associated with the entity_id
        $entityInfo = DB::table('payroll.entities')
                        ->select('database_name')
                        ->where('id', $entityId)
                        ->first();

        // Check if the query returned a result
        if (!$entityInfo) {
            return response()->json(['error' => 'Entity not found'], 404);
        }

        // Use the result's database_name
        $databaseName = $entityInfo->database_name;

        // Check if the employee exists in the dynamic database
        $employeeId = $request->input('employee_id');
        $employee = DB::table($databaseName . '.employees')->where('id', $employeeId)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Check if the employee exists in the employee_available_vacation_hours table
        $employeeVacationHours = DB::table($databaseName . '.employee_available_vacation_hours')
            ->where('employee_id', $employeeId)
            ->first();

        if (!$employeeVacationHours) {
            return response()->json([
                'error' => 'Employee does not have available vacation hours',
                'employee_id' => $employeeId
            ], 422);
        }

        // Determine the status codes based on the leave type
        $statusCodes = [];
        if ($request->input('leave_type') == 0) {
            // For leave type 0, check in VACAT
            $statusCodes = ['VACAT'];
        } elseif ($request->input('leave_type') == 1) {
            // For leave type 1, check in SICK
            $statusCodes = ['SICK'];
        }

        // Check if the employee has enough available hours for the specified leave type
        $availableHours = DB::table($databaseName . '.employee_available_vacation_hours')
                            ->where('employee_id', $employeeId)
                            ->whereIn('status_code', $statusCodes)
                            ->sum('hours');

        // Check if requested hours exceed available hours
        $requestedHours = $request->input('hours_requested');
        if ($requestedHours > $availableHours) {
            return response()->json(['error' => 'Insufficient available hours'], 422);
        }

        // Insert data into the "ess_pto_cashout_applications" table
        DB::table($databaseName . '.ess_pto_cashout_applications')->insert([
            'employee_id' => $employeeId,
            'leave_type' => $request->input('leave_type'),
            'hours_requested' => $requestedHours,
            'entity_id' => $entityId,
            'status_code' => 0, // Set status_code to 0
            'signature' => $signature,
            'first_approver' => $firstApproverId,
            'second_approver' => $secondApproverId, 
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return a success response
        return response()->json(['message' => 'Cashout request submitted successfully'], 201);
    }
}
