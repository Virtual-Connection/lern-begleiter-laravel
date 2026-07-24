<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Indexing = 'indexing';
    case Indexed = 'indexed';
    case Failed = 'failed';
}
