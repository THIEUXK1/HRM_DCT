<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends ApiController
{
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get();
        return $this->success($permissions);
    }
}
