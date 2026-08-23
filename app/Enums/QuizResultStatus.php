<?php

namespace App\Enums;

enum QuizResultStatus: string
{
    case Pending = 'pending';
    case Computing = 'computing';
    case Completed = 'completed';
    case Failed = 'failed';
}
