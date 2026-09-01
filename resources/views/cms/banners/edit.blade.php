@extends('layouts.app')
@section('title', 'Edit Banner')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="margin:0;color:#1a237e;">🖼️ Edit Banner</h2>
        <a href="{{ route('cms.banners') }}" class="btn btn-outline-secondary">← Back</a>
    </div>
    <div class="row">
        <div class="col-md-8">
            <form action="{{ route('cms.banners.update', $banner->id) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:20px;">
                    <div class="card-header" style="background:#1a237e;color:white;border-radius:12px 12px 0 0;"><strong>🖼️ Banner Content</strong></div>
                    <div class="card-body">
                        <div class="form-group mb-3"><label>Title (English) <span style="color:red">*</span></label><input type="text" name="title" class="form-control" required value="{{ old('title', $banner->title) }}"></div>
                        <div class="form-group mb-3"><label>Title (Arabic)</label><input type="text" name="title_ar" class="form-control" dir="rtl" value="{{ old('title_ar', $banner->title_ar) }}"></div>
                        <div class="form-group mb-3"><label>Subtitle (English)</label><textarea name="subtitle" class="form-control" rows="2">{{ old('subtitle', $banner->subtitle) }}</textarea></div>
                        <div class="form-group mb-3"><label>Subtitle (Arabic)</label><textarea name="subtitle_ar" class="form-control" rows="2" dir="rtl">{{ old('subtitle_ar', $banner->subtitle_ar) }}</textarea></div>
                        <div class="form-group mb-3"><label>Banner Image</label><input type="file" name="image" class="form-control" accept="image/*">
                            @if($banner->image)<br><img src="{{ asset('storage/'.$banner->image) }}" style="max-width:250px;border-radius:8px;margin-top:8px;">@endif
                        </div>
                    </div>
                </div>
                <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <div class="card-header" style="background:#00BCD4;color:white;border-radius:12px 12px 0 0;"><strong>⚙️ Settings</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6"><div class="form-group mb-3"><label>Position</label><select name="position" class="form-control"><option value="hero" {{ $banner->position=='hero'?'selected':'' }}>Hero Slider</option><option value="promo" {{ $banner->position=='promo'?'selected':'' }}>Promo Banner</option><option value="footer" {{ $banner->position=='footer'?'selected':'' }}>Footer</option></select></div></div>
                            <div class="col-md-6"><div class="form-group mb-3"><label>Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ $banner->sort_order }}"></div></div>
                        </div>
                        <div class="form-group mb-3"><label>Button Text</label><input type="text" name="button_text" class="form-control" value="{{ old('button_text', $banner->button_text) }}"></div>
                        <div class="form-group mb-3"><label>Button Text (Arabic)</label><input type="text" name="button_text_ar" class="form-control" dir="rtl" value="{{ old('button_text_ar', $banner->button_text_ar) }}"></div>
                        <div class="form-group mb-3"><label>Link URL</label><input type="text" name="link" class="form-control" value="{{ old('link', $banner->link) }}"></div>
                        <div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $banner->is_active?'checked':'' }} id="active"><label class="form-check-label" for="active">Active</label></div>
                        <button type="submit" class="btn btn-success" style="width:100%;">💾 Update Banner</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background:#4CAF50;color:white;border-radius:12px 12px 0 0;"><strong>👁️ Current Banner</strong></div>
                <div class="card-body" style="text-align:center;">
                    @if($banner->image)<img src="{{ asset('storage/'.$banner->image) }}" style="max-width:100%;border-radius:8px;">@else<p style="color:#999;padding:20px;">No image uploaded</p>@endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
