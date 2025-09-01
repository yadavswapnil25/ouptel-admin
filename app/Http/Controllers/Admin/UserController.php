<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $query = User::query();
        
        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter by role
        if ($request->has('role') && $request->role) {
            if ($request->role === 'admin') {
                $query->where(function($q) {
                    $q->where('email', 'like', '%@admin%')
                      ->orWhere('email', 'admin@ouptel.com');
                });
            } else {
                $query->where(function($q) {
                    $q->where('email', 'not like', '%@admin%')
                      ->where('email', '!=', 'admin@ouptel.com');
                });
            }
        }
        
        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        $users = $query->paginate(15);
        
        return view('admin.users', compact('users'));
    }
    
    public function show($id)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $user = User::findOrFail($id);
        return view('admin.user-detail', compact('user'));
    }
    
    public function create()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        return view('admin.user-create');
    }
    
    public function store(Request $request)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        
        return redirect()->route('admin.users')->with('success', 'User created successfully!');
    }
    
    public function edit($id)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $user = User::findOrFail($id);
        return view('admin.user-edit', compact('user'));
    }
    
    public function update(Request $request, $id)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $user = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
        ];
        
        if ($request->password) {
            $updateData['password'] = Hash::make($request->password);
        }
        
        $user->update($updateData);
        
        return redirect()->route('admin.users')->with('success', 'User updated successfully!');
    }
    
    public function destroy($id)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $user = User::findOrFail($id);
        
        // Prevent deleting the current admin user
        if ($user->id == $userId) {
            return back()->with('error', 'You cannot delete your own account!');
        }
        
        $user->delete();
        
        return redirect()->route('admin.users')->with('success', 'User deleted successfully!');
    }
    
    public function toggleStatus($id)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $user = User::findOrFail($id);
        
        // This would require a status field in the users table
        // For now, we'll just return a message
        return back()->with('info', 'Status toggle functionality will be implemented when status field is added to users table.');
    }
}






