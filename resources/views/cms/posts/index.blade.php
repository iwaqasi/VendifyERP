@extends('layouts.app')
@section('title', 'Blog Posts')

@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="margin:0; color:#1a237e;">📝 Blog Posts</h2>
            <p style="margin:4px 0 0; color:#666;">Manage your blog articles and news</p>
        </div>
        <a href="{{ route('cms.posts.create') }}" class="btn btn-success">+ New Post</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="border-radius: 8px;">{{ session('success') }}</div>
    @endif

    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        <div class="card-body" style="padding: 0;">
            <table class="table table-hover" style="margin: 0;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $post)
                    <tr>
                        <td>
                            <strong>{{ $post->title }}</strong>
                            @if($post->title_ar)
                                <br><small style="color:#888;" dir="rtl">{{ $post->title_ar }}</small>
                            @endif
                            <br><small style="color:#aaa;">/{{ $post->slug }}</small>
                        </td>
                        <td><span class="badge badge-info">{{ $post->category ?? 'Uncategorized' }}</span></td>
                        <td>{{ $post->author_name ?? 'Admin' }}</td>
                        <td>
                            @if($post->is_published)
                                <span class="badge" style="background:#4CAF50;color:white;">Published</span>
                            @else
                                <span class="badge" style="background:#ccc;color:#666;">Draft</span>
                            @endif
                        </td>
                        <td>{{ $post->views_count }}</td>
                        <td><small>{{ $post->updated_at }}</small></td>
                        <td>
                            <a href="{{ route('cms.posts.edit', $post->id) }}" class="btn btn-sm btn-outline-primary">✏️ Edit</a>
                            <form action="{{ route('cms.posts.delete', $post->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this post?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                            No blog posts yet. <a href="{{ route('cms.posts.create') }}">Create your first post</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
