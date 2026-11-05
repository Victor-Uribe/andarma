<?php
// Integrar este archivo con: require __DIR__.'/andarma_api.php'; al final de routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AndarmaAdminAuthController as Auth;
use App\Http\Controllers\Api\AndarmaAdminController as Admin;
use App\Http\Controllers\Api\AndarmaSiteContentController as Content;
use App\Http\Middleware\EnsureAndarmaAdmin;

Route::prefix('public')->group(function(){
    Route::get('/site-content',[Content::class,'index']);
    Route::get('/settings',[\App\Http\Controllers\Api\AndarmaSettingsController::class,'publicSettings']);
    Route::get('/banners',[Admin::class,'publicBanners']);
    Route::get('/developments',[Admin::class,'publicDevelopments']);
    Route::get('/lots',[Admin::class,'publicLots']);
    Route::get('/testimonials',[Admin::class,'publicTestimonials']);
    Route::post('/leads',[Admin::class,'publicLead'])->middleware('throttle:5,1');
});

Route::post('/admin/login',[Auth::class,'login'])->middleware('throttle:20,1');
Route::prefix('admin')->middleware(['auth:sanctum',EnsureAndarmaAdmin::class])->group(function(){
    Route::get('/lookup', [\App\Http\Controllers\Api\AndarmaLookupController::class, 'index']);
    Route::get('/me',[Auth::class,'me']);Route::post('/logout',[Auth::class,'logout']);Route::get('/dashboard',[Admin::class,'dashboard']);
    // ANDARMA_RBAC_V48
    Route::get('/roles',[\App\Http\Controllers\Api\AndarmaRbacController::class,'roles']);
    Route::post('/roles',[\App\Http\Controllers\Api\AndarmaRbacController::class,'storeRole']);
    Route::put('/roles/{id}',[\App\Http\Controllers\Api\AndarmaRbacController::class,'updateRole'])->whereNumber('id');
    Route::delete('/roles/{id}',[\App\Http\Controllers\Api\AndarmaRbacController::class,'deleteRole'])->whereNumber('id');
    Route::get('/users',[\App\Http\Controllers\Api\AndarmaRbacController::class,'users']);
    // ANDARMA_V50_SETTINGS_AND_USERS
    Route::get('/user-role-options',[\App\Http\Controllers\Api\AndarmaRbacController::class,'userRoleOptions']);
    Route::get('/settings',[\App\Http\Controllers\Api\AndarmaSettingsController::class,'index']);
    Route::put('/settings',[\App\Http\Controllers\Api\AndarmaSettingsController::class,'update']);
    Route::post('/users',[\App\Http\Controllers\Api\AndarmaRbacController::class,'storeUser']);
    Route::put('/users/{id}',[\App\Http\Controllers\Api\AndarmaRbacController::class,'updateUser'])->whereNumber('id');
    Route::delete('/users/{id}',[\App\Http\Controllers\Api\AndarmaRbacController::class,'deleteUser'])->whereNumber('id');
    Route::get('/site-content',[Content::class,'index']);Route::put('/site-content',[Content::class,'update']);
    Route::get('/media',[Admin::class,'media']);Route::post('/media',[Admin::class,'upload']);Route::delete('/media/{id}',[Admin::class,'deleteMedia'])->whereNumber('id');
    
// ANDARMA_CLIENTES_V30_START
Route::get('/clients/available-lots',[\App\Http\Controllers\Api\AndarmaClientsController::class,'availableLots']);
Route::get('/clients',[\App\Http\Controllers\Api\AndarmaClientsController::class,'index']);
Route::post('/clients',[\App\Http\Controllers\Api\AndarmaClientsController::class,'store']);
Route::get('/clients/{id}',[\App\Http\Controllers\Api\AndarmaClientsController::class,'show'])->whereNumber('id');
Route::put('/clients/{id}',[\App\Http\Controllers\Api\AndarmaClientsController::class,'update'])->whereNumber('id');
Route::delete('/clients/{id}',[\App\Http\Controllers\Api\AndarmaClientsController::class,'destroy'])->whereNumber('id');
Route::post('/clients/{id}/lots',[\App\Http\Controllers\Api\AndarmaClientsController::class,'assignLot'])->whereNumber('id');
Route::delete('/clients/{id}/lots/{lotId}',[\App\Http\Controllers\Api\AndarmaClientsController::class,'releaseLot'])->whereNumber('id')->whereNumber('lotId');
Route::post('/leads/{leadId}/convert',[\App\Http\Controllers\Api\AndarmaClientsController::class,'convert'])->whereNumber('leadId');
// ANDARMA_CLIENTES_V30_END
    Route::post('/banners/reorder',[Admin::class,'reorderBanners']);
    Route::get('/{resource}',[Admin::class,'index'])->where('resource','banners|developments|lots|testimonials|leads');
    Route::post('/{resource}',[Admin::class,'store'])->where('resource','banners|developments|lots|testimonials|leads');
    Route::put('/{resource}/{id}',[Admin::class,'update'])->where('resource','banners|developments|lots|testimonials|leads')->whereNumber('id');
    Route::delete('/{resource}/{id}',[Admin::class,'destroy'])->where('resource','banners|developments|lots|testimonials|leads')->whereNumber('id');
});

// ANDARMA V35: rutas protegidas de Propiedades y Mapas
require __DIR__.'/andarma_maps_v35.php';

// ANDARMA V38 — pagos, rutas protegidas
require __DIR__.'/andarma_finance_v38.php';
