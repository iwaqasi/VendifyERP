@extends('layouts.app')
@section('title', 'CMS Dashboard')

@section('content')
<style>
    .cms-stat-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-left: 4px solid; transition: transform 0.2s; }
    .cms-stat-card:hover { transform: translateY(-2px); }
    .cms-stat-card .stat-icon { font-size: 2rem; margin-bottom: 8px; }
    .cms-stat-card .stat-value { font-size: 2rem; font-weight: 700; color: #1a237e; }
    .cms-stat-card .stat-label { color: #666; font-size: 0.9rem; }
    .cms-quick-link { display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: white; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); text-decoration: none; color: #333; transition: all 0.2s; border: 1px solid #eee; }
    .cms-quick-link:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: #00BCD4; color: #00BCD4; }
    .cms-quick-link .ql-icon { font-size: 1.8rem; }
    .cms-quick-link .ql-title { font-weight: 600; font-size: 1rem; }
    .cms-quick-link .ql-desc { font-size: 0.8rem; color: #888; }
</style>

<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="margin:0; color:#1a237e;">📷 CMS Dashboard</h2>
            <p style="margin:4px 0 0; color:#666;">Manage your website content and design</p>
        </div>
        <a href="{{ route('cms.preview') }}" class="btn btn-success btn-lg" target="_blank">
            🌐 Preview Website
        </a>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="cms-stat-card" style="border-color: #00BCD4;">
                <div class="stat-icon" style="color: #00BCD4;">📄</div>
                <div class="stat-value">{{ $stats['pages'] }}</div>
                <div class="stat-label">Pages ({{ $stats['published_pages'] }} published)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="cms-stat-card" style="border-color: #4CAF50;">
                <div class="stat-icon" style="color: #4CAF50;">📝</div>
                <div class="stat-value">{{ $stats['posts'] }}</div>
                <div class="stat-label">Blog Posts ({{ $stats['published_posts'] }} published)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="cms-stat-card" style="border-color: #FF9800;">
                <div class="stat-icon" style="color: #FF9800;">🖼️</div>
                <div class="stat-value">{{ $stats['banners'] }}</div>
                <div class="stat-label">Banners / Sliders</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="cms-stat-card" style="border-color: #9C27B0;">
                <div class="stat-icon" style="color: #9C27B0;">📁</div>
                <div class="stat-value">{{ $stats['media'] }}</div>
                <div class="stat-label">Media Files</div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <h4 class="mb-3" style="color:#1a237e;">Quick Actions</h4>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.designer') }}" class="cms-quick-link">
                <div class="ql-icon">🎨</div>
                <div>
                    <div class="ql-title">Website Designer</div>
                    <div class="ql-desc">Customize colors, fonts, logo, hero banner</div>
                </div>
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.preview') }}" class="cms-quick-link" target="_blank">
                <div class="ql-icon">🌐</div>
                <div>
                    <div class="ql-title">Live Preview</div>
                    <div class="ql-desc">See your website in real-time</div>
                </div>
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.pages.create') }}" class="cms-quick-link">
                <div class="ql-icon">📄</div>
                <div>
                    <div class="ql-title">New Page</div>
                    <div class="ql-desc">Create a new static page</div>
                </div>
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.posts.create') }}" class="cms-quick-link">
                <div class="ql-icon">✏️</div>
                <div>
                    <div class="ql-title">New Blog Post</div>
                    <div class="ql-desc">Write a new blog article</div>
                </div>
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.banners.create') }}" class="cms-quick-link">
                <div class="ql-icon">🖼️</div>
                <div>
                    <div class="ql-title">New Banner</div>
                    <div class="ql-desc">Add hero slider or promo banner</div>
                </div>
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.menus') }}" class="cms-quick-link">
                <div class="ql-icon">📋</div>
                <div>
                    <div class="ql-title">Navigation Menu</div>
                    <div class="ql-desc">Manage website menu items</div>
                </div>
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.testimonials') }}" class="cms-quick-link">
                <div class="ql-icon">⭐</div>
                <div>
                    <div class="ql-title">Testimonials</div>
                    <div class="ql-desc">Customer reviews & ratings</div>
                </div>
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="{{ route('cms.media') }}" class="cms-quick-link">
                <div class="ql-icon">📁</div>
                <div>
                    <div class="ql-title">Media Library</div>
                    <div class="ql-desc">Upload & manage images</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Content -->
    <div class="row">
        <div class="col-md-6">
            <div class="card" style="border-radius: 10px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background: #1a237e; color: white; border-radius: 10px 10px 0 0;">
                    <strong>📝 Recent Blog Posts</strong>
                </div>
                <div class="card-body" style="padding: 0;">
                    @if($recent_posts->count())
                        @foreach($recent_posts as $post)
                        <div style="padding: 12px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>{{ $post->title }}</strong><br>
                                <small style="color: #888;">{{ $post->category ?? 'Uncategorized' }} • {{ $post->updated_at }}</small>
                            </div>
                            <span class="badge" style="background: {{ $post->is_published ? '#4CAF50' : '#ccc' }}; color: white;">
                                {{ $post->is_published ? 'Published' : 'Draft' }}
                            </span>
                        </div>
                        @endforeach
                    @else
                        <div style="padding: 30px; text-align: center; color: #999;">No blog posts yet. <a href="{{ route('cms.posts.create') }}">Create one</a></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card" style="border-radius: 10px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background: #00BCD4; color: white; border-radius: 10px 10px 0 0;">
                    <strong>📄 Recent Pages</strong>
                </div>
                <div class="card-body" style="padding: 0;">
                    @if($recent_pages->count())
                        @foreach($recent_pages as $page)
                        <div style="padding: 12px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>{{ $page->title }}</strong><br>
                                <small style="color: #888;">/{{ $page->slug }} • {{ $page->updated_at }}</small>
                            </div>
                            <span class="badge" style="background: {{ $page->is_published ? '#4CAF50' : '#ccc' }}; color: white;">
                                {{ $page->is_published ? 'Published' : 'Draft' }}
                            </span>
                        </div>
                        @endforeach
                    @else
                        <div style="padding: 30px; text-align: center; color: #999;">No pages yet. <a href="{{ route('cms.pages.create') }}">Create one</a></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
