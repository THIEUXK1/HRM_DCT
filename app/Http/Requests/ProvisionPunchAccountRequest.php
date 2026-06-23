<?php



namespace App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;



class ProvisionPunchAccountRequest extends FormRequest

{

    public function authorize(): bool

    {

        return true;

    }



    public function rules(): array

    {

        return [

            'punch_gps' => ['sometimes', 'boolean'],

            'punch_qr' => ['sometimes', 'boolean'],

        ];

    }



    public function withValidator($validator): void

    {

        $validator->after(function ($validator) {

            if (! $this->boolean('punch_gps') && ! $this->boolean('punch_qr')) {

                $validator->errors()->add('punch_gps', 'Chọn ít nhất quyền GPS hoặc QR.');

            }

        });

    }

}


