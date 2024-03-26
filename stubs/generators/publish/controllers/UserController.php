<?php

namespace App\Http\Controllers;

use App\Generators\Services\ImageService;
use App\Http\Requests\Users\{StoreUserRequest, UpdateUserRequest};
use App\Models\User;
use Yajra\DataTables\Facades\DataTables;
use Image;

class UserController extends Controller
{
    public function __construct(public ImageService $imageService, public string $avatarPath = '/uploads/images/avatars/')
    {
        // TODO: uncomment this code if you are using spatie permission
        // $this->middleware('permission:user view')->only('index', 'show');
        // $this->middleware('permission:user create')->only('create', 'store');
        // $this->middleware('permission:user edit')->only('edit', 'update');
        // $this->middleware('permission:user delete')->only('destroy');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
    {
        if (request()->ajax()) {
            $users = User::with('roles:id,name');

            return Datatables::of($users)
                ->addColumn('action', 'users.include.action')
                ->addColumn('role', function ($row) {
                    return $row->getRoleNames()->toArray() !== [] ? $row->getRoleNames()[0] : '-';
                })
                ->addColumn('avatar', function ($row) {
                    if ($row->avatar == null) {
                        return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($row->email))) . '&s=500';
                    }
                    return asset($this->avatarPath . $row->avatar);
                })
                ->toJson();
        }

        return view('users.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): \Illuminate\Contracts\View\View
    {
        return view('users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();

        $validated['avatar'] = $this->imageService->upload(name: 'avatar', path: $this->avatarPath, disk: 's3');

        $validated['password'] = bcrypt($request->password);

        $user = User::create($validated);

        $user->assignRole($request->role);

        return to_route('users.index')->with('success', __('The user was created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): \Illuminate\Contracts\View\View
    {
        $user->load('roles:id,name');

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): \Illuminate\Contracts\View\View
    {
        $user->load('roles:id,name');

        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();

        $validated['avatar'] = $this->imageService->upload(name: 'avatar', path: $this->avatarPath, defaultImage: $user->avatar, disk: 's3');

        switch (is_null($request->password)) {
            case true:
                unset($validated['password']);
                break;
            default:
                $validated['password'] = bcrypt($request->password);
                break;
        }

        $user->update($validated);

        $user->syncRoles($request->role);

        return to_route('users.index')->with('success', __('The user was updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): \Illuminate\Http\RedirectResponse
    {
        if ($user->avatar != null && file_exists($oldAvatar = public_path($this->avatarPath . $user->avatar))) {
            unlink($oldAvatar);
        }

        $user->delete();

        return to_route('users.index')->with('success', __('The user was deleted successfully.'));
    }
}
