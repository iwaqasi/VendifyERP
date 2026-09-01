@extends('layouts.app')
@section('title', 'Edit Page')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="margin:0;color:#1a237e;">📄 Edit: {{ $page->title }}</h2>
        <a href="{{ route('cms.pages') }}" class="btn btn-outline-secondary">← Back</a>
    </div>
    <form action="{{ route('cms.pages.update', $page->id) }}" method="POST">
        @csrf @method('PUT')
        <div class="row">
            <div class="col-md-8">
                <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:20px;">
                    <div class="card-header" style="background:#1a237e;color:white;border-radius:12px 12px 0 0;"><strong>📝 Page Content</strong></div>
                    <div class="card-body">
                        <div class="form-group mb-3"><label>Title (English) <span style="color:red">*</span></label><input type="text" name="title" class="form-control" required value="{{ old('title', $page->title) }}"></div>
                        <div class="form-group mb-3"><label>Title (Arabic)</label><input type="text" name="title_ar" class="form-control" dir="rtl" value="{{ old('title_ar', $page->title_ar) }}"></div>
                        <div class="form-group mb-3"><label>Content (English) <span style="color:red">*</span></label><textarea name="content" class="form-control" rows="15" required>{{ old('content', $page->content) }}</textarea></div>
                        <div class="form-group mb-3"><label>Content (Arabic)</label><textarea name="content_ar" class="form-control" rows="15" dir="rtl">{{ old('content_ar', $page->content_ar) }}</textarea></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <div class="card-header" style="background:#00BCD4;color:white;border-radius:12px 12px 0 0;"><strong>⚙️ Settings</strong></div>
                    <div class="card-body">
                        <div class="form-group mb-3"><label>SEO Title</label><input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $page->meta_title) }}"></div>
                        <div class="form-group mb-3"><label>SEO Description</label><textarea name="meta_description" class="form-control" rows="2">{{ old('meta_description', $page->meta_description) }}</textarea></div>
                        <div class="form-group mb-3"><label>Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $page->sort_order) }}"></div>
                        <div class="form-check mb-3"><input type="checkbox" name="is_published" class="form-check-input" value="1" {{ $page->is_published ? 'checked' : '' }} id="pub"><label class="form-check-label" for="pub">Published</label></div>
                        <div class="form-check mb-3"><input type="checkbox" name="is_homepage" class="form-check-input" value="1" {{ $page->is_homepage ? 'checked' : '' }} id="hp"><label class="form-check-label" for="hp">Homepage</label></div>
                        <button type="submit" class="btn btn-success" style="width:100%;">💾 Update Page</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
