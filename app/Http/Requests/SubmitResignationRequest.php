<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitResignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minReason = (int) config('hr_vn.resignation.min_reason_length', 20);

        return [
            'termination_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'min:'.$minReason, 'max:2000'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'handover_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'termination_date.after_or_equal' => 'Ngày nghỉ dự kiến phải từ hôm nay trở đi.',
            'reason.min' => 'Lý do xin nghỉ phải có ít nhất :min ký tự.',
        ];
    }
}
