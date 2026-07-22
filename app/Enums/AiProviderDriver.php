<?php

namespace App\Enums;

enum AiProviderDriver: string
{
    case Stub = 'stub';
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
}
