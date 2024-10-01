<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeBenefitsController extends Controller
{
  
public function index($employeeId, $entityId)
{
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

    // Use the database name dynamically in the join statement
    $employeeBenefits = DB::table("$databaseName.employee_benefits")
    ->join("$databaseName.employee_benefits_vendor as ebv", 'employee_benefits.benefits_vendor_id', '=', 'ebv.id')
    ->select('employee_benefits.*', 'ebv.vendor_name')
    ->where('employee_benefits.employee_id', $employeeId)
    ->where('employee_benefits.is_deleted', 0)  // Fetch only non-deleted records
    ->get();

    return response()->json($employeeBenefits);
}
public function getDependents($employee_id, $entity_id)
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

    // Fetch employee family background using raw SQL query
    // Only get records where is_deleted is not 1 or is null
    $familyBackground = DB::select("
        SELECT *
        FROM $databaseName.employee_family_background
        WHERE employee_id = :employee_id
        AND (is_deleted IS NULL OR is_deleted != 1)
    ", [
        'employee_id' => $employee_id,
    ]);

    return response()->json($familyBackground);
}

public function store(Request $request)
{
    $entityInfo = DB::table('payroll.entities')
                    ->select('database_name')
                    ->where('id', $request->entity_id)
                    ->first();

    // Check if the query returned a result
    if (!$entityInfo) {
        return response()->json(['error' => 'Entity not found'], 404);
    }

    // Use the result's database_name
    $databaseName = $entityInfo->database_name;

    // Insert the employee benefits data into the database
    DB::table("$databaseName.employee_benefits")->insert([
        'employee_id' => $request->employee_id,
        'dependent_id' => $request->dependent_id, 
        'amount' => $request->amount,
        'frequency' => $request->frequency,
        'benefit_type' => $request->benefit_type,
        'benefit_effectivity_date' => $request->benefit_effectivity_date,
        'benefits_description' => $request->benefits_description,
        'benefits_vendor_id' => $request->benefits_vendor_id,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
        'premium_cot' => $request->premium_cot,
        'unit_id' => $request->unit_id,
        'plan_type' => $request->plan_type,
        'benefits_option' => $request->benefits_option,
        'benefits_coverage' => $request->benefits_coverage,
        'benefits_limit' => $request->benefits_limit,
        'effective_date' => $request->effective_date,
        'benefits_file_link' => $request->benefits_file_link,
        'created_by' => $request->employee_id,
        'updated_by' => $request->employee_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['message' => 'Employee benefits inserted successfully']);
}


public function updateBenefit(Request $request)
{
    $request->validate([
        'id' => 'required|integer',
        'entity_id' => 'required|integer',
        'dependent_id' => 'required|integer',
        'plan_type' => 'required|integer',
        'employee_id' => 'required|integer',
        'benefit_type' => 'required|string',
        'amount' => 'required|integer',
        'frequency' => 'required|string',
        'benefit_effectivity_date' => 'required|string',
    ]);

    $entityInfo = DB::table('payroll.entities')
                    ->select('database_name')
                    ->where('id', $request->entity_id)
                    ->first();

    // Check if the query returned a result
    if (!$entityInfo) {
        return response()->json(['error' => 'Entity not found'], 404);
    }

    // Use the result's database_name
    $databaseName = $entityInfo->database_name;

    // Update the employee benefits data in the database
    $updated = DB::table("$databaseName.employee_benefits")
        ->where('id', $request->id)
        ->update([
            'dependent_id' => $request->dependent_id,
            'plan_type' => $request->plan_type,
            'updated_by' => $request->employee_id,
            'benefit_type' => $request->benefit_type,
            'amount' => $request->amount,
            'frequency' => $request->frequency,
            'benefit_effectivity_date' => $request->benefit_effectivity_date,
            'updated_at' => now(),
        ]);

    if ($updated) {
        return response()->json(['message' => 'Employee benefits updated successfully']);
    } else {
        return response()->json(['error' => 'Employee benefits not found or no changes made'], 404);
    }
}

public function removeBenefit(Request $request)
{
    // Validate the incoming request
    $request->validate([
        'id' => 'required|integer',
        'entity_id' => 'required|integer',
        'employee_id' => 'required|integer',
    ]);

    // Fetch the database name from the entity
    $entityInfo = DB::table('payroll.entities')
                    ->select('database_name')
                    ->where('id', $request->entity_id)
                    ->first();

    // Check if the query returned a result
    if (!$entityInfo) {
        return response()->json(['error' => 'Entity not found'], 404);
    }

    // Use the result's database_name
    $databaseName = $entityInfo->database_name;

    // Perform the soft delete by setting is_deleted to 1
    $updated = DB::table("$databaseName.employee_benefits")
        ->where('id', $request->id)
        ->update([
            'is_deleted' => 1, // Mark as soft deleted
            'updated_by' => $request->employee_id,
            'updated_at' => now(),
        ]);

    // Return response based on success or failure
    if ($updated) {
        return response()->json(['message' => 'Employee benefit soft deleted successfully']);
    } else {
        return response()->json(['error' => 'Employee benefit not found or already deleted'], 404);
    }
}


public function addDependent(Request $request)
{
    $validator = Validator::make($request->all(), [
        'entity_id' => 'required|integer',
        'employee_id' => 'required|integer',
        'lastname' => 'required|string|max:255',
        'firstname' => 'required|string|max:255',
        'relationship' => 'required|string|max:255',
        'birthdate' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 400);
    }

    try {
        // Fetch the database name associated with the entity_id
        $entityInfo = DB::table('payroll.entities')
                        ->select('database_name')
                        ->where('id', $request->entity_id)
                        ->first();

        // Check if the query returned a result
        if (!$entityInfo) {
            return response()->json(['error' => 'Entity not found'], 404);
        }

        $databaseName = $entityInfo->database_name;

        $result = DB::table($databaseName . '.employee_family_background')->insert([
            'employee_id' => $request->employee_id,
            'lastname' => $request->lastname,
            'firstname' => $request->firstname,
            'relationship' => $request->relationship,
            'birthdate' => $request->birthdate,
            'created_at' => now(),
        ]);

        if ($result) {
            return response()->json(['message' => 'Record inserted successfully'], 201);
        } else {
            return response()->json(['message' => 'Failed to insert record'], 500);
        }
    } catch (\Exception $e) {
        return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
    }
}

public function updateDependent(Request $request)
{
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer',
        'entity_id' => 'required|integer',
        'employee_id' => 'required|integer',
        'lastname' => 'required|string|max:255',
        'firstname' => 'required|string|max:255',
        'relationship' => 'required|string|max:255',
        'birthdate' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 400);
    }

    try {
        // Fetch the database name associated with the entity_id
        $entityInfo = DB::table('payroll.entities')
                        ->select('database_name')
                        ->where('id', $request->entity_id)
                        ->first();

        // Check if the query returned a result
        if (!$entityInfo) {
            return response()->json(['error' => 'Entity not found'], 404);
        }

        $databaseName = $entityInfo->database_name;

        // Check if the record exists
        $recordExists = DB::table($databaseName . '.employee_family_background')
            ->where('id', $request->id)
            ->exists();

        if (!$recordExists) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $result = DB::table($databaseName . '.employee_family_background')
            ->where('id', $request->id)
            ->update([
                'employee_id' => $request->employee_id,
                'lastname' => $request->lastname,
                'firstname' => $request->firstname,
                'relationship' => $request->relationship,
                'birthdate' => $request->birthdate,
                'updated_at' => now(),
            ]);

        if ($result) {
            return response()->json(['message' => 'Record updated successfully'], 200);
        } else {
            return response()->json(['message' => 'No changes were made to the record'], 200);
        }
    } catch (\Exception $e) {
        return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
    }
}

public function deleteDependent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'entity_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            // Fetch the database name associated with the entity_id
            $entityInfo = DB::table('payroll.entities')
                            ->select('database_name')
                            ->where('id', $request->entity_id)
                            ->first();

            // Check if the query returned a result
            if (!$entityInfo) {
                return response()->json(['error' => 'Entity not found'], 404);
            }

            $databaseName = $entityInfo->database_name;

            // Check if the record exists
            $recordExists = DB::table($databaseName . '.employee_family_background')
                ->where('id', $request->id)
                ->exists();

            if (!$recordExists) {
                return response()->json(['error' => 'Record not found'], 404);
            }

            $result = DB::table($databaseName . '.employee_family_background')
                ->where('id', $request->id)
                ->update([
                    'is_deleted' => 1,
                    'deleted_at' => now(),
                ]);

            if ($result) {
                return response()->json(['message' => 'Record soft deleted successfully'], 200);
            } else {
                return response()->json(['message' => 'No changes were made to the record'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }
  
}
