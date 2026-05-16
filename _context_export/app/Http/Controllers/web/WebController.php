<?php

namespace App\Http\Controllers\web;

use inertia\inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WebController extends Controller
{
    /**
     * Start Home Page
    **/
    public function home(){
        return Inertia::render('Student/Theme1/Index');
    }
    /**
     * End Home Page
    **/
    /**
     * Start Lessons Page
    **/
    public function lessons(){
        return Inertia::render('Student/Theme1/Lessons');
    }
    /**
     * End lessons Page
    **/
    /**
     * Start teachers Page
    **/
    public function teachers(){
        return Inertia::render('Student/Theme1/Teachers');
    }
    /**
     * End teachers Page
    **/
    /**
     * Start blog Page
    **/
    public function blog(){
        return Inertia::render('Student/Theme1/Blog');
    }
    /**
     * End blog Page
    **/

    /**
     * Show single lesson with files
     */
    public function showLesson($id)
    {
        $lesson = \App\Models\Lesson::with(['lectures.files', 'category'])
            ->findOrFail($id);

        return Inertia::render('Student/Theme1/LessonView', [
            'lesson' => $lesson,
        ]);
    }
    
}
