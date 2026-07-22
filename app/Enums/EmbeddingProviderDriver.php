<?php

namespace App\Enums;

enum EmbeddingProviderDriver: string
{
    case Stub = 'stub';
    case OpenAi = 'openai';
}
