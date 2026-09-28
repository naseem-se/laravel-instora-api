<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('users.view');

        $paginated = User::query()
            ->where('company_id', $context->requireCompanyId())
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(UserResource::collection($paginated)->response()->getData(true));
    }

    public function store(StoreUserRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('users.create');

        $user = $this->users->create($request->validated(), $context, $request->user());

        return ApiResponse::success(new UserResource($user), 'User created successfully.', 201);
    }

    public function show(int $id, CompanyContext $context): JsonResponse
    {
        $this->authorize('users.view');

        $user = $this->findOwned(User::class, $id, $context);

        return ApiResponse::success(new UserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorize('users.update');

        /** @var User $user */
        $user = $this->findOwned(User::class, $id, $context);
        $user = $this->users->update($user, $request->validated(), $request->user());

        return ApiResponse::success(new UserResource($user), 'User updated successfully.');
    }
}