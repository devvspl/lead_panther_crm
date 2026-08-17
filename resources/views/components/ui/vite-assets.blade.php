@if(file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        canvas: '#F9FAFB',
                        surface: '#FFFFFF',
                        ink: '#111827',
                        muted: '#6B7280',
                        border: '#E5E7EB',
                        accent: '#111827',
                        primary: '#4F46E5',
                    },
                    borderRadius: {
                        'card': '0.75rem',
                        'pill': '9999px',
                    }
                }
            }
        }
    </script>
@endif
