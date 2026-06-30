<?php

namespace App\Support\FormBuilder;

class FormValidationError
{
    public function __construct(
        public string $fieldKey,
        public string $rule,
        public string $messageAr,
        public string $messageEn,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(string $locale = 'ar'): array
    {
        return [
            'field_key' => $this->fieldKey,
            'rule' => $this->rule,
            'message' => $locale === 'en' ? $this->messageEn : $this->messageAr,
            'message_ar' => $this->messageAr,
            'message_en' => $this->messageEn,
        ];
    }
}
