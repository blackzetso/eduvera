<?php

namespace App\Http\Controllers\teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\admin\FileController as AdminFileController;
use App\Models\File;
use App\Models\Lecture;
use Illuminate\Http\Request;

class LessonFileController extends Controller
{
    public function __construct(private AdminFileController $adminFiles) {}

    public function uploadToBunny(Request $request)
    {
        $this->assertOwnsLecture($request->input('lecture_id'));

        return $this->adminFiles->uploadToBunny($request);
    }

    public function saveYoutubeLink(Request $request)
    {
        $this->assertOwnsLecture($request->input('lecture_id'));

        return $this->adminFiles->saveYoutubeLink($request);
    }

    public function saveExternalLink(Request $request)
    {
        $this->assertOwnsLecture($request->input('lecture_id'));

        return $this->adminFiles->saveExternalLink($request);
    }

    public function update(Request $request, File $file)
    {
        $this->assertOwnsFile($file);

        return $this->adminFiles->update($request, $file);
    }

    public function destroy(Request $request, File $file)
    {
        $this->assertOwnsFile($file);

        return $this->adminFiles->destroy($file);
    }

    private function assertOwnsLecture(?int $lectureId): void
    {
        abort_if(! $lectureId, 403);

        $lecture = Lecture::with('lesson')->findOrFail($lectureId);
        abort_if($lecture->lesson->teacher_id !== auth()->id(), 403);
    }

    private function assertOwnsFile(File $file): void
    {
        $file->load('lecture.lesson');
        abort_if($file->lecture->lesson->teacher_id !== auth()->id(), 403);
    }
}
