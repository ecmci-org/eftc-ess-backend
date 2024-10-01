<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use phpseclib\Net\SFTP;


class PdfController extends Controller
{
    public function fetchPdf(Request $request)
    {
        // Validate request body
        $request->validate([
            'user_id' => 'required|integer',
            'employee_id' => 'required|integer',
            'filename' => 'required|string'
        ]);

        // Get user_id, employee_id, and filename from request body
        $user_id = $request->input('user_id');
        $employee_id = $request->input('employee_id');
        $fileName = $request->input('filename');
        $employeeName = $request->input('employee_name');

        // SFTP connection settings
        $host = '98.154.109.77';
        $username = 'Administrator';
        $password = 'MsSue789';
        $port = 22;

        // Root directory of the SFTP server
        $sftpRoot = "/C:/Users/Administrator/Desktop/EFTPR_Paystubs/payroll@snfacilityclients.compst file/Inbox/{$employeeName}/";

        // Initialize SFTP connection
        $sftp = new SFTP($host, $port);
        if (!$sftp->login($username, $password)) {
            return response()->json(['error' => 'Failed to connect to SFTP server'], 500);
        }

        // Change directory to the root directory
        if (!$sftp->chdir($sftpRoot)) {
            return response()->json(['error' => 'Failed to change directory on SFTP server'], 500);
        }

        // Download the PDF file
        $pdfContent = $sftp->get($fileName);
        if ($pdfContent === false) {
            return response()->json(['error' => 'File not found or unable to read'], 404);
        }

        // Return PDF file content as response
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf');
           // ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
    public function getAllFiles(Request $request)
    {
        // Validate request body
        $request->validate([
            'user_id' => 'required|integer',
            'employee_id' => 'required|integer',
            'employee_name' => 'required|string',
        ]);
    
        // Get user_id, employee_id, and employee_name from request body
        $user_id = $request->input('user_id');
        $employee_id = $request->input('employee_id');
        $employeeName = $request->input('employee_name');
    
        // SFTP connection settings
        $host = '98.154.109.77';
        $username = 'Administrator';
        $password = 'MsSue789';
        $port = 22;
    
        // Root directory of the SFTP server
        $sftpRoot = "/C:/Users/Administrator/Desktop/EFTPR_Paystubs/payroll@snfacilityclients.compst file/Inbox/{$employeeName}";
    
        // Initialize SFTP connection
        $sftp = new SFTP($host, $port);
        if (!$sftp->login($username, $password)) {
            return response()->json(['error' => 'Failed to connect to SFTP server'], 500);
        }
    
        // Change directory to the root directory
        if (!$sftp->chdir($sftpRoot)) {
            return response()->json(['error' => 'Failed to change directory on SFTP server'], 500);
        }
    
        // List all files in the current directory
        $files = $sftp->nlist();
    
        // Initialize array to store file details
        $pdfFiles = [];
    
        // Iterate through each file
        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            // Check if the file has a PDF extension
            if (strtolower($extension) === 'pdf') {
                $size = $sftp->size($file);
                $sizeInKB = $size !== false ? round($size / 1024, 2) : null;
                // Add file details to the array
                $pdfFiles[] = [
                    'name' => $file,
                    'path' => $sftpRoot . $file,
                    'date' => date('Y-m-d H:i:s', $sftp->filemtime($file)),
                    'size' => $sizeInKB
                ];
            }
        }
    
        // Check if any PDF files were found
        if (empty($pdfFiles)) {
            return response()->json(['pdf_files' => []], 200);
        }
    
        // Return list of PDF files
        return response()->json(['pdf_files' => $pdfFiles], 200);
    }

    public function getAllCredentials($employee_id)
    {
        $databaseName = 'payroll_ecmci';
        $query = DB::table($databaseName . '.employee_credentials');
        
        if ($employee_id) {
            $query->where('employee_id', $employee_id);
        }
        
        $credentials = $query->get();
        
        return response()->json($credentials);
    }
    
}
