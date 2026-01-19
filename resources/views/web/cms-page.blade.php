<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $seoTitle }}</title>
    
    @if($seoDescription)
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($seoDescription, 160) }}">
    @endif
    
    @if($seoKeywords)
    <meta name="keywords" content="{{ is_string($seoKeywords) ? $seoKeywords : implode(', ', $seoKeywords) }}">
    @endif
    
    <meta name="robots" content="index, follow">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #1a1a1a;
            border-bottom: 3px solid #e0e0e0;
            padding-bottom: 1rem;
        }
        
        .content {
            margin-top: 2rem;
            font-size: 1.1rem;
        }
        
        .content p {
            margin-bottom: 1rem;
        }
        
        .content h2 {
            font-size: 1.8rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: #2a2a2a;
        }
        
        .content h3 {
            font-size: 1.4rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: #3a3a3a;
        }
        
        .content ul, .content ol {
            margin-left: 2rem;
            margin-bottom: 1rem;
        }
        
        .content li {
            margin-bottom: 0.5rem;
        }
        
        .content a {
            color: #0066cc;
            text-decoration: underline;
        }
        
        .content a:hover {
            color: #0052a3;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 2rem;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link:hover {
            color: #333;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ $webUrl ?? '/' }}" class="back-link">← Back to Home</a>
        
        <h1>{{ $page->title }}</h1>
        
        <div class="content">
            {!! $page->content !!}
        </div>
    </div>
</body>
</html>

