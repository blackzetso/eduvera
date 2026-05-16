<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['name','parent_id'];
    
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'category_subject');
    }

    /**
     * التحقق من كون هذه الفئة عقدة نهائية (leaf node)
     * @return bool
     */
    public function isLeafNode(): bool
    {
        // تحميل الأطفال إذا لم يتم تحميلهم
        if (!$this->relationLoaded('children')) {
            $this->load('children');
        }
        
        return $this->children->count() === 0;
    }

    /**
     * الحصول على جميع العقد النهائية (leaf descendants) تحت هذه الفئة
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLeafDescendants()
    {
        // تحميل الأطفال إذا لم يتم تحميلهم
        if (!$this->relationLoaded('children')) {
            $this->load('children');
        }

        // إذا كانت هذه الفئة leaf node، أرجعها
        if ($this->isLeafNode()) {
            return collect([$this]);
        }

        // البحث التكراري في جميع الفروع
        $leafNodes = collect();
        
        foreach ($this->children as $child) {
            $leafDescendants = $child->getLeafDescendants();
            $leafNodes = $leafNodes->merge($leafDescendants);
        }

        return $leafNodes;
    }

    /**
     * الحصول على جميع العقد النهائية من قائمة من الفئات
     * @param array $categoryIds
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getLeafNodesFromIds(array $categoryIds)
    {
        $leafNodes = collect();
        
        $categories = self::with('children')->whereIn('id', $categoryIds)->get();
        
        foreach ($categories as $category) {
            $leafNodes = $leafNodes->merge($category->getLeafDescendants());
        }

        // إزالة التكرار باستخدام unique على id
        return $leafNodes->unique('id');
    }

    /**
     * الحصول على جميع الفئات الفرعية (بما في ذلك الفئة نفسها)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllDescendants()
    {
        $descendants = collect([$this]);
        
        foreach ($this->children as $child) {
            $descendants = $descendants->merge($child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * التحقق من وجود دروس مرتبطة بهذه الفئة أو فروعها
     * @return bool
     */
    public function hasLessonsInTree()
    {
        $allDescendants = $this->getAllDescendants();
        $categoryIds = $allDescendants->pluck('id');
        
        return \App\Models\Lesson::whereIn('category_id', $categoryIds)->exists();
    }

    /**
     * عد الدروس في هذه الفئة وجميع فروعها
     * @return int
     */
    public function countLessonsInTree()
    {
        $allDescendants = $this->getAllDescendants();
        $categoryIds = $allDescendants->pluck('id');
        
        return \App\Models\Lesson::whereIn('category_id', $categoryIds)->count();
    }

    /**
     * الحصول على مستوى الفئة في الشجرة (0 للجذر، 1 للمستوى الأول، إلخ)
     * @return int
     */
    public function getDepthLevel()
    {
        $level = 0;
        $parent = $this->parent;
        
        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }
        
        return $level;
    }
}
