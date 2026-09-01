@extends('layouts.app')
@section('title', 'New Blog Post')

@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="margin:0; color:#1a237e;">✏️ New Blog Post</h2>
        <a href="{{ route('cms.posts') }}" class="btn btn-outline-secondary">← Back to Posts</a>
    </div>

    <form action="{{ route('cms.posts.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-8">
                <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px;">
                    <div class="card-header" style="background: #1a237e; color: white; border-radius: 12px 12px 0 0;">
                        <strong>📝 Post Content</strong>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Title (English) <span style="color:red">*</span></label>
                            <input type="text" name="title" class="form-control" required value="{{ old('title') }}">
                        </div>
                        <div class="form-group mb-3">
                            <label>Title (Arabic)</label>
                            <input type="text" name="title_ar" class="form-control" dir="rtl" value="{{ old('title_ar') }}">
                        </div>
                        <div class="form-group mb-3">
                            <label>Excerpt</label>
                            <textarea name="excerpt" class="form-control" rows="2">{{ old('excerpt') }}</textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label>Content (English) <span style="color:red">*</span></label>
                            <textarea name="content" class="form-control" rows="15" required>{{ old('content') }}</textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label>Content (Arabic)</label>
                            <textarea name="content_ar" class="form-control" rows="15" dir="rtl">{{ old('content_ar') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px;">
                    <div class="card-header" style="background: #00BCD4; color: white; border-radius: 12px 12px 0 0;">
                        <strong>⚙️ Settings</strong>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Category</label>
                            <input type="text" name="category" class="form-control" value="{{ old('category') }}" placeholder="e.g., Fashion, News">
                        </div>
                        <div class="form-group mb-3">
                            <label>Tags</label>
                            <input type="text" name="tags" class="form-control" value="{{ old('tags') }}" placeholder="tag1, tag2, tag3">
                        </div>
                        <div class="form-group mb-3">
                            <label>Author Name</label>
                            <input type="text" name="author_name" class="form-control" value="{{ old('author_name', auth()->user()->first_name . ' ' . auth()->user()->last_name) }}">
                        </div>
                        <div class="form-group mb-3">
                            <label>Featured Image</label>
                            <input type="file" name="featured_image" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group mb-3">
                            <label>SEO Title</label>
                            <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title') }}">
                        </div>
                        <div class="form-group mb-3">
                            <label>SEO Description</label>
                            <textarea name="meta_description" class="form-control" rows="2">{{ old('meta_description') }}</textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_published" class="form-check-input" value="1" checked id="is_published">
                            <label class="form-check-label" for="is_published">Published</label>
                        </div>
                        <button type="submit" class="btn btn-success btn-block" style="width:100%;">💾 Create Post</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
