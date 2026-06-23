<?php



namespace App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;



class LoginRequest extends FormRequest

{

    public function authorize(): bool

    {

        return true;

    }



    public function rules(): array

    {

        return [

            'login' => ['required_without:email', 'string', 'max:255'],

            'email' => ['required_without:login', 'email', 'max:255'],

            'password' => ['required', 'string', 'min:6'],

        ];

    }



    public function loginIdentifier(): string

    {

        return trim((string) ($this->input('login') ?? $this->input('email')));

    }

}


