<?php

namespace App\Http\Controllers\admin;

use Inertia\Inertia;
use App\Models\Language;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\LanguagePhrase;

class LanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $languages = Language::orderBy('id', 'ASC')->paginate(10);
        return Inertia::render('Admin/theme1/Settings/Language',compact('languages'));
    }

    public function toggleStatus($id) {
        $language = Language::findOrFail($id);
        $language->status = $language->status === 'enabled' ? 'disabled' : 'enabled';
        $language->save();

        return redirect()->route('admin.language.index')->with('success', 'تم تحديث الحالة بنجاح');
    }
    /**
     * Show the form for creating a new resource.
     */

    public function search($phrase, Request $request)
    {
        $languages = Language::where('name', 'like', '%' . $phrase . '%')
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return inertia::render('Admin/theme1/Settings/Language', [
            'languages' => $languages,
            'filters' => ['search' => $phrase],
        ]);
    }


    public function create()
    {
        return Inertia::render('Admin/theme1/Settings/LanguageCreate');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:languages,code',
        ]);

        try {
            // ✅ إنشاء اللغة الجديدة
            $language = Language::create([
                'name'   => $validated['name'],
                'code'   => $validated['code'],
                'status' => 'enabled',
            ]);

            // ✅ جلب اللغة المرجعية (is_demo = 1)
            $referenceLang = Language::where('is_default', 1)->first();

            if ($referenceLang) {
                // ✅ جلب الكلمات الخاصة باللغة المرجعية
                $phrases = LanguagePhrase::where('language_id', $referenceLang->id)->get();

                // ✅ نسخ الكلمات للغة الجديدة
                foreach ($phrases as $phrase) {
                    LanguagePhrase::create([
                        'language_id' => $language->id,
                        'group'       => $phrase->group,
                        'key'         => $phrase->key,
                        'word'        => $phrase->word,
                    ]);
                }
            }

            return redirect()->back()->with('success', 'تم إنشاء اللغة بنجاح ونسخ الكلمات من اللغة المرجعية.');
        } catch (\Exception $e) {
            dd($e->getMessage());
            return redirect()->back()->withErrors(['error' => 'حدث خطأ أثناء الحفظ: ' . $e->getMessage()]);
        }
    }



    /**
     * Display the specified resource.
     */


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Language $language)
    {
        $words = LanguagePhrase::where('language_id',$language->id)->orderBy('id', 'DESC')->get();
        return Inertia::render('Admin/theme1/Settings/LanguagePhrase',compact('words','language'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Language $language)
    {
        if ($request->type === 'language') {
            // تحديث اسم اللغة
            $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255',
            ]);

            $language->update([
                'name' => $request->name,
            ]);

            return back()->with('success', 'تم تحديث اللغة بنجاح');
        }

        if ($request->type === 'word') {
            // تحديث كلمة
            $request->validate([
                'word_id' => 'required|exists:language_phrases,id',
                'word' => 'required|string|max:255',
            ]);

            $word = LanguagePhrase::find($request->word_id);
            $word->update([
                'word' => $request->word,
            ]);

            return back()->with('success', 'تم تحديث الكلمة بنجاح');
        }

        return back()->with('error', 'طلب غير صالح');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Language $language)
    {
        $language->delete();
        return redirect()->back();
    }
}
