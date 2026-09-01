@extends('layouts.app')
@section('title', 'CMS Pages')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 style="margin:0;color:#1a237e;">📄 Pages</h2><p style="margin:4px 0 0;color:#666;">Manage static pages (About, Terms, etc.)</p></div>
        <a href="{{ route('cms.pages.create') }}" class="btn btn-success">+ New Page</a>
    </div>
    @if(session('success'))<div class="alert alert-success" style="border-radius:8px;">{{ session('success') }}</div>@endif
    <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <div class="card-body" style="padding:0;">
            <table class="table table-hover" style="margin:0;">
                <thead style="background:#f8f9fa;"><tr><th>Title</th><th>Slug</th><th>Status</th><th>Homepage</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($pages as $page)
                    <tr>
                        <td><strong>{{ $page->title }}</strong>@if($page->title_ar)<br><small style="color:#888;" dir="rtl">{{ $page->title_ar }}</small>@endif</td>
                        <td><small>/{{ $page->slug }}</small></td>
                        <td><span class="badge" style="background:{{ $page->is_published ? '#4CAF50' : '#ccc' }};color:white;">{{ $page->is_published ? 'Published' : 'Draft' }}</span></td>
                        <td>{{ $page->is_homepage ? '🏠 Yes' : 'No' }}</td>
                        <td><small>{{ $page->updated_at }}</small></td>
                        <td>
                            <a href="{{ route('cms.pages.edit', $page->id) }}" class="btn btn-sm btn-outline-primary">✏️</a>
                            <form action="{{ route('cms.pages.delete', $page->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑️</button></form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;padding:40px;color:#999;">No pages yet. <a href="{{ route('cms.pages.create') }}">Create one</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
