<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'assignee_id' => 'sometimes|exists:users,id',
            'status' => 'sometimes|in:todo,in_progress,done',
            'priority' => 'sometimes|in:low,medium,high',
            'due_date' => 'sometimes|date|after:today',
        ];
    }
}
