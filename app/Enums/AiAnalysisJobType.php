<?php

namespace App\Enums;

enum AiAnalysisJobType: string
{
    case DocumentAnalyse = 'document_analyse';
    case DraftGenerate = 'draft_generate';
    case VulnerabilityTriage = 'vulnerability_triage';
    case RagIndex = 'rag_index';
}
