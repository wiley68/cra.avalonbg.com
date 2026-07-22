<?php

namespace App\Enums;

enum AiConversationContextType: string
{
    case Chat = 'chat';
    case DocumentAnalyser = 'document_analyser';
    case Draft = 'draft';
    case VulnerabilityTriage = 'vulnerability_triage';
}
