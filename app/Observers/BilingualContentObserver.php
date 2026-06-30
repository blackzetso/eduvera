<?php

namespace App\Observers;

use App\Services\Translation\BilingualAutoTranslationService;
use Illuminate\Database\Eloquent\Model;

class BilingualContentObserver
{
    public function __construct(
        protected BilingualAutoTranslationService $translator,
    ) {}

    public function saving(Model $model): void
    {
        if (! $this->translator->isEnabled()) {
            return;
        }

        if ($model->getAttribute('_skip_bilingual_translation') === true) {
            return;
        }

        $this->translator->translateModel($model);
    }
}
