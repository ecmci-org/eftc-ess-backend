<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use ParagonIE\CipherSweet\Backend\FIPSCrypto;
use ParagonIE\CipherSweet\EncryptedField;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;
use ParagonIE\CipherSweet\CipherSweet;

class KekController extends Controller
{
    public function getKek(Request $request)
    {
        // Get the encryption key from the environment

        $key = env('CIPHERSWEET_KEY');



        // Initialize CipherSweet
        $provider = new StringProvider($key);
        $cipherSweet = new CipherSweet($provider);

        // Get the ID from the request
        $id = $request->input('id');

        // Fetch encrypted user data from the database
        $encryptedUserData = DB::table('payroll.users')->where('id', $id)->first();

        if (!$encryptedUserData) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Decrypt encrypted fields
        $encryptedFieldFirstName = new EncryptedField($cipherSweet, 'payroll.users', 'first_name');
        $encryptedFieldLastName = new EncryptedField($cipherSweet, 'payroll.users', 'last_name');
        $encryptedFieldSSN = new EncryptedField($cipherSweet, 'payroll.users', 'ssn');
        $encryptedFieldCellphone = new EncryptedField($cipherSweet, 'payroll.users', 'cellphone');
        $encryptedFieldTelephone = new EncryptedField($cipherSweet, 'payroll.users', 'telephone');

        $firstName = $encryptedFieldFirstName->decryptValue($encryptedUserData->first_name);
        $lastName = $encryptedFieldLastName->decryptValue($encryptedUserData->last_name);
        $ssn = $encryptedFieldSSN->decryptValue($encryptedUserData->ssn);
        $cellphone = $encryptedFieldCellphone->decryptValue($encryptedUserData->cellphone);
        $telephone = $encryptedFieldTelephone->decryptValue($encryptedUserData->telephone);
        // Decrypt other encrypted fields as needed...

        // Prepare decrypted user data
        $decryptedUserData = (array) $encryptedUserData;
        $decryptedUserData['first_name'] = $firstName;
        $decryptedUserData['last_name'] = $lastName;
        $decryptedUserData['ssn'] = $ssn;
        $decryptedUserData['cellphone'] = $cellphone;
        $decryptedUserData['telephone'] = $telephone;
        // Include other decrypted fields...

        return response()->json($decryptedUserData);
    }
}
