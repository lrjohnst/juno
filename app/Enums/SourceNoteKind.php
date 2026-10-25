<?php

namespace App\Enums;

enum SourceNoteKind: string
{
    case Summary = 'Summary';
    case Note = 'Note';
    case Quote = 'Quote';
}
