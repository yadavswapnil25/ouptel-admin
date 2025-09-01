<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Get current user from session
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }
        
        $user = User::find($userId);
        
        // Basic statistics
        $totalUsers = User::count();
        $adminUsers = User::where('email', 'like', '%@admin%')
            ->orWhere('email', 'admin@ouptel.com')
            ->count();
        $newUsersToday = User::whereDate('created_at', today())->count();
        $newUsersThisWeek = User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)->count();
        
        // User growth data for charts
        $userGrowthData = $this->getUserGrowthData();
        
        // Recent users
        $recentUsers = User::latest()->take(5)->get();
        
        // User registration by month (last 12 months)
        $monthlyRegistrations = $this->getMonthlyRegistrations();
        
        // Top performing metrics
        $metrics = [
            'total_users' => $totalUsers,
            'admin_users' => $adminUsers,
            'new_users_today' => $newUsersToday,
            'new_users_this_week' => $newUsersThisWeek,
            'new_users_this_month' => $newUsersThisMonth,
            'active_users' => $totalUsers, // Assuming all users are active for now
            'total_posts' => 0, // Placeholder - will be implemented when posts module is added
            'total_pages' => 0, // Placeholder - will be implemented when pages module is added
            'total_groups' => 0, // Placeholder - will be implemented when groups module is added
            'online_users' => 0, // Placeholder - will be implemented when online tracking is added
            'total_comments' => 0, // Placeholder - will be implemented when comments module is added
            'total_games' => 0, // Placeholder - will be implemented when games module is added
            'total_messages' => 0, // Placeholder - will be implemented when messages module is added
        ];
        
        return view('admin.dashboard', compact(
            'user', 
            'metrics', 
            'recentUsers', 
            'userGrowthData', 
            'monthlyRegistrations'
        ));
    }
    
    private function getUserGrowthData()
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = User::whereDate('created_at', $date)->count();
            $data[] = [
                'date' => $date->format('M d'),
                'count' => $count
            ];
        }
        return $data;
    }
    
    private function getMonthlyRegistrations()
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $data[] = [
                'month' => $date->format('M Y'),
                'count' => $count
            ];
        }
        return $data;
    }
    
    public function getStats()
    {
        $totalUsers = User::count();
        $adminUsers = User::where('email', 'like', '%@admin%')
            ->orWhere('email', 'admin@ouptel.com')
            ->count();
        $newUsersToday = User::whereDate('created_at', today())->count();
        $newUsersThisWeek = User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        
        return response()->json([
            'total_users' => $totalUsers,
            'admin_users' => $adminUsers,
            'new_users_today' => $newUsersToday,
            'new_users_this_week' => $newUsersThisWeek,
            'active_users' => $totalUsers,
        ]);
    }
    
    public function getUserGrowth()
    {
        $data = $this->getUserGrowthData();
        return response()->json($data);
    }
    
    public function getMonthlyData()
    {
        $data = $this->getMonthlyRegistrations();
        return response()->json($data);
    }
}






