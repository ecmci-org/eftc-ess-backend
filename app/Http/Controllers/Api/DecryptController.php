<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use ParagonIE\CipherSweet\Backend\FIPSCrypto;
use ParagonIE\CipherSweet\EncryptedField;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;
use ParagonIE\CipherSweet\CipherSweet;

class DecryptController extends Controller
{
    public function decryptUserData(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            // Add validation rules for each encrypted field
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'ssn' => 'required|string',
            'cellphone' => 'required|string',
            'telephone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get the encryption key from the environment
        $key = env('CIPHERSWEET_KEY');

        // Initialize CipherSweet
        $provider = new StringProvider($key);
        $cipherSweet = new CipherSweet($provider);

        // Get the encrypted user data from the request body
        $encryptedUserData = $request->all();

        // Decrypt each encrypted field in the request body
        $decryptedUserData = [];

        foreach ($encryptedUserData as $field => $value) {
            $encryptedField = new EncryptedField($cipherSweet, 'payroll.users', $field);
            try {
                $decryptedUserData[$field] = $encryptedField->decryptValue($value);
            } catch (\Throwable $e) {
                // Handle decryption errors
                return response()->json(['error' => 'Failed to decrypt data.'], 500);
            }
        }

        return response()->json($decryptedUserData);
    }
}
