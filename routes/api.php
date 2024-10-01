<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeTimecardsController;
use App\Http\Controllers\Api\PTOCashoutRequestController;
use App\Http\Controllers\Api\EmployeeHireHistoryController;
use App\Http\Controllers\Api\EmployeeBenefitsController;
use App\Http\Controllers\Api\DocuSignController;
use App\Http\Controllers\Api\DocuSignAuthController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\ECMCI\TestController;
use App\Http\Controllers\Api\ECMCI\SampolController;
use App\Http\Controllers\Api\KekController;
use App\Http\Controllers\Api\UserInfoController;
use App\Http\Controllers\Api\DecryptController;
use App\Http\Controllers\Api\V1\ECMCI\HakdogController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('employees',[EmployeeTimecardsController::class, 'getEmployeeTimecards']);
Route::get('/sample-api', [TestController::class, 'getString']);
Route::get('/showInfo', [KekController::class, 'getKek']);
Route::get('/users', [UserInfoController::class, 'getUserInfo']);
Route::get('/getUserEntity', [UserInfoController::class, 'getUserEntityID']);
Route::get('/generateURL', [UserInfoController::class, 'generateSignedUrl']);
Route::get('/getPathName/{entity_id}', [UserInfoController::class, 'getPathName']);
Route::get('/getnotifications/{user_id}/{entity_id}', [UserInfoController::class, 'getNotifications']);
Route::get('/getPayday/{id}', [UserInfoController::class, 'getPayday']);
Route::get('/download/{filePath}', [UserInfoController::class, 'downloadFile']);
Route::post('/decrypt', [DecryptController::class, 'decryptUserData']);
Route::get('/hakdog', [HakdogController::class, 'getHakdog']);
Route::get('/cashouthistory', [PTOCashoutRequestController::class, 'index']);
Route::post('/applycashout', [PTOCashoutRequestController::class, 'store']);
Route::post('/getpaystub', [PdfController::class, 'fetchPdf']);
Route::post('/getpaystublist', [PdfController::class, 'getAllFiles']);
Route::get('/getcredentials/{employee_id}', [PdfController::class, 'getAllCredentials']);
Route::get('/timeline/{employee_id}/{entity_id}', [EmployeeHireHistoryController::class, 'index']);
Route::get('/getbenefits/{employee_id}/{entity_id}', [EmployeeBenefitsController::class, 'index']);
Route::get('/getdependents/{employee_id}/{entity_id}', [EmployeeBenefitsController::class, 'getDependents']);
Route::post('/enrollbenefits', [EmployeeBenefitsController::class, 'store']);
Route::post('/updatebenefit', [EmployeeBenefitsController::class, 'updateBenefit']);
Route::post('/removebenefit', [EmployeeBenefitsController::class, 'removeBenefit']);
Route::post('/addfamily', [EmployeeBenefitsController::class, 'addDependent']);
Route::post('/updatefamily', [EmployeeBenefitsController::class, 'updateDependent']);
Route::post('/removefamily', [EmployeeBenefitsController::class, 'deleteDependent']);
Route::get('/getChanges/{employee_id}/{entity_id}/{date}', [EmployeeHireHistoryController::class, 'getChanges']);
Route::put('/saveChangeNotice', [UserInfoController::class, 'saveChangeNotice']);
Route::put('/updatePersonalEmail', [UserInfoController::class, 'updatePersonalEmail']);
Route::post('/s3fileupload', [UserInfoController::class, 'uploadfileS3']);


Route::get('/auth/docusign', [DocuSignAuthController::class, 'docusignRedirect']);
Route::get('/auth/docusign/callback', [DocuSignAuthController::class, 'docusignCallback']);
Route::post('/auth/getsignature', [DocuSignAuthController::class, 'signDocument']);



// Route::get('docusign',[DocusignController::class, 'index'])->name('docusign');
// Route::get('connect-docusign',[DocusignController::class, 'connectDocusign'])->name('connect.docusign');
// Route::get('docusign/callback',[DocusignController::class,'callback'])->name('docusign.callback');
// Route::get('sign-document',[DocusignController::class,'signDocument'])->name('docusign.sign');