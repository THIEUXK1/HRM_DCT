<?php



namespace App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rules\Password;



class ChangePasswordRequest extends FormRequest

{

    public function authorize(): bool

    {

        return (bool) $this->user();

    }



    public function rules(): array

    {

        $defaultPassword = config('attendance_vn.punch_default_password', 'abc@123');



        return [

            'current_password' => ['required', 'string'],

            'password' => [

                'required',

                'string',

                'confirmed',

                Password::min(8)->mixedCase()->numbers(),

                'not_in:'.$defaultPassword,

            ],

        ];

    }



    public function messages(): array

    {

        return [

            'password.not_in' => 'Mật khẩu mới không được trùng mật khẩu mặc định.',

        ];

    }

}


