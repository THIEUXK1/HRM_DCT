<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\PositionRequest;
use App\Models\Position;

class PositionController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Position::class, 'position');
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        return $this->success(Position::with('department')->orderBy('name')->get());
    }

    public function store(PositionRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $position = Position::create($data);

        return $this->success($position, 201);
    }

    public function show(Position $position): \Illuminate\Http\JsonResponse
    {
        return $this->success($position->load('department'));
    }

    public function update(PositionRequest $request, Position $position): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $position->update($data);

        return $this->success($position);
    }

    public function destroy(Position $position): \Illuminate\Http\JsonResponse
    {
        $position->delete();

        return $this->noContent();
    }
}
