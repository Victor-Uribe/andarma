<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AndarmaInventoryMapsController as IM;
use App\Http\Middleware\EnsureAndarmaAdmin;
Route::prefix('admin')->middleware(['auth:sanctum',EnsureAndarmaAdmin::class])->group(function(){
    Route::get('/im/permissions',[IM::class,'permissions']);
    Route::get('/im/developments',[IM::class,'developments']);
    Route::get('/im/developments/summary',[IM::class,'developmentSummary']);
    // ANDARMA_V37_DEVELOPMENTS: administración e imagen WebP validada por módulo
    Route::post('/im/developments/reorder',[\App\Http\Controllers\Api\AndarmaDevelopmentV37Controller::class,'reorder']);
    Route::post('/im/developments/save',[\App\Http\Controllers\Api\AndarmaDevelopmentV37Controller::class,'create']);
    Route::post('/im/developments/{id}/save',[\App\Http\Controllers\Api\AndarmaDevelopmentV37Controller::class,'update'])->whereNumber('id');
    Route::delete('/im/developments/{id}',[IM::class,'deleteDevelopment'])->whereNumber('id');
    Route::get('/im/inventory',[IM::class,'inventory']);
    Route::post('/im/inventory',[IM::class,'createLot']);
    Route::put('/im/inventory/{id}',[IM::class,'updateLot'])->whereNumber('id');
    Route::delete('/im/inventory/{id}',[IM::class,'deleteLot'])->whereNumber('id');
    Route::get('/im/maps',[IM::class,'plans']);
    Route::post('/im/maps',[IM::class,'createPlan']);
    Route::put('/im/maps/{id}/title',[IM::class,'renamePlan'])->whereNumber('id');
    Route::get('/im/maps/{id}',[IM::class,'plan'])->whereNumber('id');
    Route::delete('/im/maps/{id}',[IM::class,'deletePlan'])->whereNumber('id');
    Route::post('/im/maps/{id}/polygons',[IM::class,'savePolygon'])->whereNumber('id');
    Route::put('/im/maps/{id}/polygons/{geometryId}',[IM::class,'updatePolygon'])->whereNumber('id')->whereNumber('geometryId');
    Route::delete('/im/maps/{id}/polygons/{geometryId}',[IM::class,'deletePolygon'])->whereNumber('id')->whereNumber('geometryId');
});
