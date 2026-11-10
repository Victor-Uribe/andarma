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
    // ANDARMA_ANALYTICS_F1_20261105
    Route::post('/analytics/{event}',[\App\Http\Controllers\Api\AndarmaAnalyticsController::class,'record'])->where('event','visit|whatsapp')->middleware('throttle:30,1');
});

// ANDARMA_PUBLIC_MAP_FASE2: consulta pública segura de planos y polígonos.
Route::get('/public/maps', [\App\Http\Controllers\Api\AndarmaPublicMapsController::class, 'index']);
Route::get('/public/maps/{id}', [\App\Http\Controllers\Api\AndarmaPublicMapsController::class, 'show'])->whereNumber('id');

Route::post('/admin/login',[Auth::class,'login'])->middleware('throttle:20,1');
Route::prefix('admin')->middleware(['auth:sanctum',EnsureAndarmaAdmin::class])->group(function(){
    Route::get('/lookup', [\App\Http\Controllers\Api\AndarmaLookupController::class, 'index']);
    Route::get('/me',[Auth::class,'me']);Route::post('/logout',[Auth::class,'logout']);Route::get('/dashboard',[Admin::class,'dashboard']);
    Route::get('/analytics',[\App\Http\Controllers\Api\AndarmaAnalyticsController::class,'index']);
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
// ANDARMA_FASE20_AUDITORIA_CLIENTES: antes de /clients/{id}
Route::get('/clients/audit',[\App\Http\Controllers\Api\AndarmaClientsController::class,'auditIndex']);
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
    Route::get('/{resource}',[Admin::class,'index'])->where('resource','banners|developments|testimonials|leads');
    Route::post('/{resource}',[Admin::class,'store'])->where('resource','banners|testimonials|leads');
    Route::put('/{resource}/{id}',[Admin::class,'update'])->where('resource','banners|testimonials|leads')->whereNumber('id');
    Route::delete('/{resource}/{id}',[Admin::class,'destroy'])->where('resource','banners|testimonials|leads')->whereNumber('id');
});

// ANDARMA_FASE1_PREPROD_V1: las rutas CRUD heredadas ya no escriben terrenos ni desarrollos.
// ANDARMA V35: rutas protegidas de Propiedades y Mapas
require __DIR__.'/andarma_maps_v35.php';

// ANDARMA V38 — pagos, rutas protegidas
require __DIR__.'/andarma_finance_v38.php';

// ANDARMA_FASE3A_GALERIA_HERO - Galería pública de desarrollos activos.
\Illuminate\Support\Facades\Route::get('/public/development-gallery', [\App\Http\Controllers\Api\AndarmaDevelopmentGalleryController::class, 'publicIndex']);
// Gestión protegida: se verifica además developments.edit en cada escritura.
\Illuminate\Support\Facades\Route::prefix('admin')->middleware(['auth:sanctum', \App\Http\Middleware\EnsureAndarmaAdmin::class])->group(function(){
    \Illuminate\Support\Facades\Route::get('/development-gallery/{id}', [\App\Http\Controllers\Api\AndarmaDevelopmentGalleryController::class, 'index'])->whereNumber('id');
    \Illuminate\Support\Facades\Route::post('/development-gallery/{id}', [\App\Http\Controllers\Api\AndarmaDevelopmentGalleryController::class, 'store'])->whereNumber('id');
    // ANDARMA_FASE3D_GALERIA_UNIFICADA: portada y reemplazo usando developments.edit.
    \Illuminate\Support\Facades\Route::post('/development-gallery/{id}/{photoId}/primary', [\App\Http\Controllers\Api\AndarmaDevelopmentGalleryController::class, 'primary'])->whereNumber('id')->whereNumber('photoId');
    \Illuminate\Support\Facades\Route::post('/development-gallery/{id}/{photoId}/replace', [\App\Http\Controllers\Api\AndarmaDevelopmentGalleryController::class, 'replace'])->whereNumber('id')->whereNumber('photoId');
    \Illuminate\Support\Facades\Route::post('/development-gallery/{id}/reorder', [\App\Http\Controllers\Api\AndarmaDevelopmentGalleryController::class, 'reorder'])->whereNumber('id');
    \Illuminate\Support\Facades\Route::delete('/development-gallery/{id}/{photoId}', [\App\Http\Controllers\Api\AndarmaDevelopmentGalleryController::class, 'destroy'])->whereNumber('id')->whereNumber('photoId');
});
