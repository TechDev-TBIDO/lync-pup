<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InformationSheetFile extends Model
{
    use HasFactory;

    protected $primaryKey = 'information_sheet_file_id';

    protected $fillable = ['info_sheet_id', 'file_path', 'original_filename', 'is_image'];

    protected $casts = ['is_image' => 'boolean'];

    public function informationSheet()
    {
        return $this->belongsTo(InformationSheet::class, 'info_sheet_id', 'info_sheet_id');
    }

    public function getExtensionAttribute(): string
    {
        return strtoupper(pathinfo($this->original_filename, PATHINFO_EXTENSION));
    }

    public function getUrlAttribute(): string
    {
        // ?name= so StorageController serves this under the founder's own
        // filename instead of file_path's generated on-disk name — see
        // that controller's docblock for why it matters most for a type
        // (Word, Excel) the browser has no inline viewer for, where View
        // and Download both just end in a save either way.
        return Storage::disk('public')->url($this->file_path)
            .'?name='.rawurlencode($this->original_filename);
    }
}
