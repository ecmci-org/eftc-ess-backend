<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use ParagonIE\CipherSweet\Backend\FIPSCrypto;
use ParagonIE\CipherSweet\EncryptedField;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;
use ParagonIE\CipherSweet\CipherSweet;
use Aws\S3\S3Client;
use App\Utils\S3Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class UserInfoController extends Controller
{
    public function getUserInfo(Request $request) {
        $id = $request->input('id');
        
        $user = DB::table('payroll.users')->where('id', $id)->first();
    
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
    
        return response()->json($user);
    }

    public function getUserEntityID(Request $request)
    {
        // Execute the query to fetch the entity_id
        $user_id = $request->input('user_id');
        $entityId = DB::table('payroll.entity_user')
                     ->select('entity_id')
                     ->where('user_id', $user_id)
                     ->where('primary', 1)
                     ->value('entity_id');

        // Check if entity_id was found
        if ($entityId === null) {
            return response()->json(['error' => 'Entity not found'], 404);
        }

        // Return the entity_id as JSON response
        return response()->json(['entity_id' => $entityId], 200);
    }

    public function getPayday($id)
    {
        // Execute the SQL query to fetch the payroll calendar record by its ID
        $payrollCalendar = DB::table('payroll.payroll_calendars')
                            ->where('id', $id)
                            ->first();

        // Check if the payroll calendar record exists
        if ($payrollCalendar === null) {
            return response()->json(['error' => 'Payroll calendar not found'], 404);
        }

        // Return the payroll calendar record as JSON response
        return response()->json($payrollCalendar, 200);
    }

  
public function saveChangeNotice(Request $request)
{
    // Define validation rules
    $rules = [
        'employee_id' => 'required|integer',
        'entity_id' => 'required|integer',
        'notice_old_values' => 'required|string',
        'notice_new_values' => 'required|string',
 
    ];

    // Validate the request
    $validator = \Validator::make($request->all(), $rules);

    // Check if validation fails
    if ($validator->fails()) {
        // Return validation error response
        return response()->json(['error' => $validator->errors()->first()], 422);
    }

    // Additional validation to ensure non-string integers
    $fields = ['employee_id', 'entity_id'];
    foreach ($fields as $field) {
        if (is_string($request->input($field))) {
            return response()->json(['error' => "The $field must be a non-string integer."], 422);
        }
    }

    // Fetch the entity_id from the request
    $entityId = $request->input('entity_id');
    $noticeOldValue = $request->input('notice_old_values');
    $noticeNewValue = $request->input('notice_new_values');


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

 

    // Insert data into the "ess_employee_change_notice" table
    $changeNoticeId = DB::table($databaseName . '.ess_employee_change_notice')->insertGetId([
        'employee_id' => $employeeId,
        'notice_type' => 2,
        'notice_sub_type' => 2,
        'reason' => "Number Change",
        'status' => 1, // Set status_code to 0
        'notice_old_values' => $noticeOldValue,
        'notice_new_values' => $noticeNewValue,
        'created_by' => $employeeId, 
        'updated_by' => $employeeId, 
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Check if insertion was successful
    if (!$changeNoticeId) {
        return response()->json(['error' => 'Failed to insert change notice'], 500);
    }
    
    // Insert data into another table using the $changeNoticeId
    // Example:
    DB::table($databaseName . '.ess_employee_change_notice_approval_history')->insert([
        'employee_change_notice_id' => $changeNoticeId,
        'level' => 1,
        'reviewer' => $employeeId,
        'reviewer_at' => $employeeId,
        'status_code' => 1,
        'created_by' => $employeeId,
        'updated_by' => $employeeId,
        'created_at' => now(),
        'updated_at' => now(),
        // Other fields...
    ]);

    // Return a success response
    return response()->json(['message' => 'Updated information successfully'], 201);
}
public function updatePersonalEmail(Request $request)
{
    // Validate request parameters
    $request->validate([
        'personal_email' => 'required|email',
    ]);

    $user_id = $request->input('user_id');

    // Check if the user exists
    $user = DB::table('payroll.users')->where('id', $user_id)->first();
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // Update the personal_email for the user with the specified user_id
    $updated = DB::table('payroll.users')
                ->where('id', $user_id)
                ->update(['personal_email' => $request->input('personal_email')]);

    // Check if any rows were affected
    if ($updated) {
        // Return a success response
        return response()->json(['message' => 'Personal email updated successfully'], 200);
    } else {
        // Return an error response if no rows were affected
        return response()->json(['error' => 'Personal email remained unchanged'], 400);
    }
}

public function getPathName($entity_id)
{
    // Perform a raw query to retrieve the logo_path from the entities table
    $logoPath = DB::table('payroll.entities')
    ->where('id', $entity_id)
    ->value('logo_path');

    // Check if the logo_path was found
    if (!$logoPath) {
    return response()->json(['error' => 'Entity not found'], 404);
    }

    // Return the logo_path in the response
    return response()->json(['path_name' => $logoPath]);
}

public function generateSignedUrl(Request $request)
{
    // Validate the incoming request to ensure 'path_name' is provided
    $request->validate([
        'path_name' => 'required|string',
    ]);

    $pathName = $request->query('path_name');
    $expiration = Carbon::now()->addMinutes(10); // Change this to your desired expiry time

    // Log::info('Generating signed URL for key: ' . $pathName); // Log the key for debugging

    try {
        $url = S3Helper::getTemporaryUrl($pathName, $expiration);

        if ($url) {
            return response()->json(['url' => $url]);
        } else {
            return response()->json(['error' => 'Unable to generate signed URL'], 500);
        }
    } catch (\Exception $e) {
        Log::error('Error generating signed URL: ' . $e->getMessage());
        return response()->json(['error' => 'Unable to generate signed URL'], 500);
    }
}

public function downloadFile($filePath)
{
    // Decode the file path
    $decodedFilePath = urldecode($filePath);

    // Check if the file exists in the S3 bucket
    if (Storage::disk('s3')->exists($decodedFilePath)) {
        // Determine the file's content type based on its extension
        $extension = pathinfo($decodedFilePath, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeType($extension);

        // Get the file's content
        $fileContent = Storage::disk('s3')->get($decodedFilePath);

        // Create a response with the file's content
        return response($fileContent, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . basename($decodedFilePath) . '"');
    } else {
        return response()->json(['error' => 'File not found'], 404);
    }
}
public function getNotifications($user_id, $entity_id)
{
    try {
        // Fetch the database name associated with the entity_id
        $entityInfo = DB::table('payroll.entities')
                        ->select('database_name')
                        ->where('id', $entity_id)
                        ->first();

        if (!$entityInfo) {
            return response()->json(['error' => 'Entity not found'], 404);
        }

        $databaseName = $entityInfo->database_name;

        // Fetch user-specific notifications
        $notifications = DB::select("
            SELECT * FROM {$databaseName}.notifications
            WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.user_id')) = ?
            ORDER BY created_at DESC
        ", [$user_id]);

        return response()->json($notifications);

    } catch (\Exception $e) {
        \Log::error('Error fetching notifications: ' . $e->getMessage());
        return response()->json(['error' => 'An error occurred while fetching notifications'], 500);
    }
}
public function uploadfileS3(Request $request)
{
    // Validate the request
    $request->validate([
        'file' => 'required|file|mimes:jpg,png,pdf,docx|max:2048', // Adjust as needed
    ]);

    // Test S3 connection
    try {
        $filesInBucket = Storage::disk('s3')->files();
        Log::info('Files in S3 Bucket: ' . json_encode($filesInBucket));
    } catch (\Exception $e) {
        Log::error('Failed to list files in S3 bucket: ' . $e->getMessage());
        return response()->json(['msg' => 'Failed to connect to S3. Error: ' . $e->getMessage()], 500);
    }

    try {
        // Retrieve the original filename
        $originalFilename = $request->file('file')->getClientOriginalName();
        Log::info('Original filename: ' . $originalFilename);

        // Generate a unique filename
        $filename = Str::uuid() . '_' . $originalFilename;

        // Store the file in the S3 bucket
        $path = Storage::disk('s3')->putFileAs('credentials', $request->file('file'), $filename, 'public');
        Log::info('Attempted to store file. Path: ' . ($path ?: 'empty'));

        // Check if the path is empty
        if (!$path) {
            throw new \Exception('Failed to store file: Path is empty');
        }

        // Retrieve the file's URL
        $url = Storage::disk('s3')->url($path);
        Log::info('File URL: ' . $url);

        // Get file metadata
        $size = $request->file('file')->getSize();
        $mimeType = $request->file('file')->getMimeType();

        return response()->json([
            'path' => $path,
            'url' => $url,
            'original_filename' => $originalFilename,
            'stored_filename' => $filename,
            'size' => $size,
            'mime_type' => $mimeType,
            'msg' => 'File uploaded successfully!',
        ]);

    } catch (S3Exception $e) {
        Log::error('S3 Error: ' . $e->getMessage());
        return response()->json(['msg' => 'S3 Error: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        Log::error('Error uploading file: ' . $e->getMessage());
        return response()->json(['msg' => 'Failed to upload file. Error: ' . $e->getMessage()], 500);
    }
}


private function getMimeType($extension)
{
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        // Add more mime types as needed
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

}
