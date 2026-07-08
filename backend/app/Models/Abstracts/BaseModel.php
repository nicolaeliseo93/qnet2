<?php

namespace App\Models\Abstracts;

use App\Models\Concerns\HasCustomFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasCustomFields;
    use HasFactory;
}
