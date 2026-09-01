@extends('layouts.app')
@section('title', 'Media Library')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 style="margin:0;color:#1a237e;">📁 Media Library</h2><p style="margin:4px 0 0;color:#666;">Upload and manage images for your website</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success" style="border-radius:8px;">{{ session('success') }}</div>@endif
    <div class="card mb-4" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <div class="card-header" style="background:#1a237e;color:white;border-radius:12px 12px 0 0;"><strong>📤 Upload Files</strong></div>
        <div class="card-body">
            <form action="{{ route('cms.media.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-8"><div class="form-group mb-0"><input type="file" name="files[]" class="form-control" multiple accept="image/*,.pdf,.mp4"></div></div>
                    <div class="col-md-3"><input type="text" name="alt_text" class="form-control" placeholder="Alt text (optional)"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-success">Upload</button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="row">
        @forelse($media as $m)
        <div class="col-md-2 mb-3">
            <div class="card" style="border-radius:8px;border:none;box-shadow:0 2px 6px rgba(0,0,0,0.08);overflow:hidden;">
                @if(str_starts_with($m->file_type, 'image/'))
                    <img src="{{ asset('storage/'.$m->file_path) }}" style="width:100%;height:120px;object-fit:cover;">
                @else
                    <div style="width:100%;height:120px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:2rem;">📄</div>
                @endif
                <div class="card-body" style="padding:8px;">
                    <small style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $m->file_name }}">{{ $m->file_name }}</small>
                    <small style="color:#999;">{{ number_format($m->file_size/1024, 1) }} KB</small>
                    <form action="{{ route('cms.media.delete', $m->id) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" style="margin-top:4px;width:100%;">🗑️</button></form>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12" style="text-align:center;padding:60px;color:#999;">No media files yet. Upload some images above.</div>
        @endforelse
    </div>
</div>
@endsection
