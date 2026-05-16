<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Facades\Hash;

class RegisterApiController extends Controller
{
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'تم التسجيل بنجاح',
            'data' => $categories
        ]);

    }

    // تسجيل طالب جديد
    public function register(Request $request)
    {

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'phone'             => 'required|string|max:20|unique:users,phone',
            'guardian_phone'    => 'required|string|max:20',
            'password'          => 'required|string|min:6',
            'category_id'       => 'required|exists:categories,id', // السنة الدراسية
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'phone'          => $data['phone'],
            'guardian_phone' => $data['guardian_phone'],
            'password'       => Hash::make($data['password']),
            'category_id'    => $data['category_id'], // حفظ السنة الدراسية
            'user_type'      => 'student',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم التسجيل بنجاح',
            'user' => $user
        ]);
    }
}
