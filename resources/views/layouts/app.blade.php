<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vizra SDK - @yield('title', 'Dashboard')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS CDN with dark mode config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Livewire Styles -->
    @livewireStyles

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        /* Custom scrollbar styles for dark theme */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgb(31 41 55); /* gray-800 */
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgb(75 85 99); /* gray-600 */
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgb(107 114 128); /* gray-500 */
        }

        /* Prose styles for better text readability */
        .prose p {
            margin-bottom: 0.75rem;
        }

        .prose p:last-child {
            margin-bottom: 0;
        }

        /* JSON viewer specific styles */
        .json-content pre {
            scrollbar-width: thin;
            scrollbar-color: #374151 #1f2937;
        }

        .json-content pre::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .json-content pre::-webkit-scrollbar-track {
            background: #1f2937;
            border-radius: 3px;
        }

        .json-content pre::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }

        .json-content pre::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        .json-content pre::-webkit-scrollbar-corner {
            background: #1f2937;
        }
    </style>
</head>
<body class="font-inter antialiased bg-gray-950 text-white">
    <div class="min-h-screen bg-gray-950 flex flex-col">
        <!-- Top Navigation -->
        <header class="bg-gray-900/30 backdrop-blur-xl border-b border-gray-800 sticky top-0 z-50">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center space-x-8">
                    <!-- Logo -->
                    <div class="flex-shrink-0">
                        <a href="{{ route('vizra.dashboard') }}" class="flex items-center space-x-2 group">
                            <div style="width:100px" class="group-hover:opacity-90 transition-opacity">
                                <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                     width="100%" height="30px" viewBox="0 0 800 230" style="enable-background:new 0 0 800 230;" xml:space="preserve">
                                <style type="text/css">
                                    .st0{fill:none;stroke:#0E508B;stroke-width:2;}
                                    .st1{fill:none;stroke:#164589;stroke-width:2;}
                                    .st2{fill:none;stroke:#213689;stroke-width:2;}
                                    .st3{fill:none;stroke:#3C158A;stroke-width:2;}
                                    .st4{fill:none;stroke:#026C8C;stroke-width:2;}
                                    .st5{fill:none;stroke:#0CB1FE;stroke-width:2;}
                                    .st6{fill:none;stroke:#2189FB;stroke-width:2;}
                                    .st7{fill:none;stroke:#3470F9;stroke-width:2;}
                                    .st8{fill:none;stroke:#5A40F9;stroke-width:2;}
                                    .st9{fill:none;stroke:#81858C;stroke-width:2;}
                                    .st10{fill:#00CDFF;}
                                    .st11{fill:#1894FC;}
                                    .st12{fill:#297EF9;}
                                    .st13{fill:#FFFFFF;}
                                    .st14{fill:#3E61F8;}
                                    .st15{fill:#751FFA;}
                                </style>
                                <g>
                                    <path class="st0" d="M79.45,84.8l17.75,33.9"/>
                                    <path class="st1" d="M97.2,118.7l13.46,25.38"/>
                                    <path class="st2" d="M110.66,144.08l17.11,32.84"/>
                                    <path class="st3" d="M127.77,176.92c4.79,9.17,9.57,18.33,14.34,27.48c5.11,9.82,12.95,14.06,23.53,12.71
                                        c5.34-0.68,10.05-3.16,12.97-7.25c3.75-5.27,6.91-11.35,9.79-17.39c9.44-19.79,18.77-39.6,28-59.43"/>
                                    <path class="st2" d="M216.4,133.04l33.74-70.58"/>
                                    <path class="st1" d="M250.14,62.46l19.43-40.7"/>
                                    <path class="st0" d="M269.57,21.76l1.2-2.7c0.09-0.2,0-0.43-0.21-0.52c-0.05-0.02-0.1-0.03-0.15-0.03
                                        c-9.03-0.03-21.66-0.04-37.91-0.04c-10.32-0.01-23.49-0.78-34.97-0.36c-12.95,0.47-21.05,7.67-26.24,18.93
                                        c-10.67,23.15-21.62,46.63-32.86,70.44c-0.37,0.8-0.13,1.8,0.73,3"/>
                                    <path class="st1" d="M139.16,110.48l11.21,20.46c0.1,0.19,0.34,0.26,0.53,0.16c0.08-0.04,0.14-0.1,0.17-0.18l29.35-61.31"/>
                                    <path class="st0" d="M180.42,69.61c4.28-8.07,8.38-16.11,12.31-24.14c1.53-3.13,3.67-4.82,6.4-5.07
                                        c7.39-0.65,17.24-0.44,29.56,0.65"/>
                                    <path class="st1" d="M228.69,41.05c3.07,0.62,4.19,2.83,3.37,6.63c-0.33,1.51-1.39,4.13-3.19,7.87
                                        c-4.4,9.15-8.79,18.31-13.18,27.48"/>
                                    <path class="st2" d="M215.69,83.03l-19.26,41.42"/>
                                    <path class="st3" d="M196.43,124.45c-5.2,10.71-10.3,21.46-15.29,32.27c-6.43,13.91-10.25,21.99-11.44,24.22
                                        c-0.99,1.85-2.61,3.46-4.88,4.81c-3.78,2.26-8.44-1.01-10.15-4.18c-1.98-3.69-3.95-7.37-5.9-11.04"/>
                                    <path class="st2" d="M148.77,170.53l-25.26-50.07"/>
                                    <path class="st1" d="M123.51,120.46l-5.68-10.7"/>
                                    <path class="st0" d="M117.83,109.76L94.82,66.11"/>
                                    <path class="st4" d="M94.82,66.11c6.54-8.75,8.65-17.89,6.32-27.42C97.17,22.45,82.42,12.74,65.8,14.93
                                        c-28.18,3.72-36.73,39.66-14.6,56.62c6.5,4.98,14.34,6.91,23.52,5.79c0.34-0.04,0.67,0.13,0.84,0.43l3.89,7.03"/>
                                    <path class="st5" d="M94.82,66.11C87.43,70.4,82.3,76.63,79.45,84.8"/>
                                    <path class="st6" d="M117.83,109.76l0.56,3.3c0,0.02-0.02,0.03-0.03,0.03c-0.01,0-0.01,0-0.02-0.01l-10.05-18.33
                                        c-0.15-0.28-0.5-0.38-0.77-0.23c-0.01,0-0.02,0.01-0.03,0.02c-3.85,2.33-7.97,5.27-12.36,8.84c-0.85,0.7-1.54,1.38-2.07,2.03
                                        c-0.41,0.49-0.52,1.17-0.28,1.77l4.42,11.52"/>
                                    <path class="st7" d="M123.51,120.46c-0.99-0.19-1.82-0.68-2.49-1.47c-0.26-0.31-0.69-0.38-1.03-0.18l-15.64,9.03
                                        c-0.32,0.18-0.44,0.57-0.28,0.9c2.42,4.92,5.71,9.97,6.59,15.34"/>
                                    <path class="st8" d="M148.77,170.53c-2.31-2.23-4.22-4.78-5.72-7.65c-0.14-0.26-0.39-0.45-0.69-0.49l-0.52-0.08
                                        c-0.21-0.03-0.41,0.12-0.45,0.34c0,0.01,0,0.02,0,0.03c-0.01,0.18-0.09,0.34-0.23,0.48c-4.59,4.52-9.05,9.11-13.39,13.76"/>
                                    <path class="st8" d="M216.4,133.04l4.69-12.88c0.17-0.47-0.07-0.98-0.55-1.16c-0.09-0.03-0.19-0.05-0.29-0.05
                                        c-3.15-0.05-5.98,0.33-8.49,1.15c-3.15,1.03-6.99,2.74-11.52,5.15c-1.96,1.04-3.85,3.61-5.67,7.71c-0.05,0.11-0.17,0.16-0.28,0.12
                                        h-0.01c-0.15-0.06-0.22-0.23-0.17-0.37c0-0.01,0-0.01,0.01-0.02l3.72-8.14c0.05-0.12,0-0.25-0.12-0.3
                                        c-0.04-0.02-0.09-0.02-0.13-0.02l-1.16,0.22"/>
                                    <path class="st7" d="M250.14,62.46c0.3-4.37-0.94-7.1-5.88-5.95c-5.23,1.21-10.15,2.16-14.74,2.84c-0.28,0.04-0.53,0.21-0.67,0.46
                                        c-3.46,6.08-6.85,12.74-10.18,19.99c-0.3,0.64-1.29,1.72-2.98,3.23"/>
                                    <path class="st6" d="M269.57,21.76c-3.36-1.33-5.81-1.84-7.36-1.53c-5.53,1.1-13.6,4.01-24.21,8.73
                                        c-4.26,1.89-9.11,5.02-14.56,9.38c-0.11,0.09-0.12,0.24-0.03,0.36c0.03,0.03,0.06,0.06,0.09,0.07l5.19,2.28"/>
                                    <path class="st6" d="M180.42,69.61l6.26-16.26c0.06-0.16-0.02-0.34-0.18-0.4c-0.06-0.02-0.12-0.03-0.18-0.01
                                        c-1.46,0.38-3.31,0.94-4.64,1.77c-3.56,2.22-7.69,4.05-12.39,5.5c-1.45,0.44-4.33,2.12-8.65,5.05c-0.32,0.21-0.58,0.49-0.78,0.81
                                        c-0.8,1.33-1.45,2.51-1.96,3.55c-6.36,13.11-12.15,25.44-17.38,36.98c-0.25,0.56-0.71,1.85-1.36,3.88"/>
                                    <path class="st9" d="M464,54.81c0-7.06-5.72-12.78-12.78-12.78l0,0c-7.06,0-12.78,5.72-12.78,12.78s5.72,12.78,12.78,12.78
                                        C458.28,67.59,464,61.87,464,54.81L464,54.81"/>
                                    <path class="st9" d="M304.26,45.67c0.21,0.38,0.54,1.24,1,2.57c12.81,37.23,26.94,78.33,42.41,123.32
                                        c0.95,2.76,2.86,7.89,5.73,15.39c0.12,0.3,0.41,0.5,0.74,0.5h24.25c0.27,0,0.51-0.17,0.59-0.42l48.33-141.68
                                        c0.03-0.1-0.03-0.2-0.12-0.22c-0.02,0-0.03-0.01-0.05-0.01h-22.39c-0.37,0-0.71,0.24-0.83,0.6l-36.79,112.25
                                        c-0.09,0.31-0.42,0.48-0.72,0.39c-0.19-0.06-0.33-0.2-0.39-0.39L329.54,45.65c-0.1-0.31-0.39-0.52-0.72-0.52h-24.24
                                        c-0.2,0-0.36,0.16-0.36,0.36C304.22,45.55,304.23,45.62,304.26,45.67"/>
                                    <path class="st9" d="M603.21,96.22l-1.24-11.76c-0.02-0.22-0.2-0.38-0.42-0.38h-19.52c-0.2,0-0.36,0.16-0.36,0.36c0,0,0,0,0,0
                                        v102.25c0,0.49,0.39,0.88,0.87,0.88h20.2c0.22,0,0.4-0.18,0.4-0.4v0c-0.02-19.19-0.02-37.53,0-55c0.01-4.29-0.12-8.43,1.01-12.3
                                        c2.16-7.39,6.85-12.35,14.07-14.9c7.25-2.55,13.89-2.59,21.37-2.09c0.26,0.02,0.48-0.18,0.5-0.43c0-0.01,0-0.02,0-0.03V83.18
                                        c0-0.37-0.28-0.67-0.65-0.69c-5.79-0.37-12.67-0.78-18.37,0.77c-7.59,2.07-13.48,6.4-17.67,13.01c-0.03,0.05-0.09,0.06-0.14,0.03
                                        C603.23,96.28,603.22,96.25,603.21,96.22"/>
                                    <path class="st9" d="M722.08,187.38h18.87c0.24,0,0.43-0.2,0.43-0.44c0.08-14.59,0.08-35.98-0.01-64.17
                                        c-0.01-3.28-0.28-6.74-0.81-10.39c-2.45-16.99-13.75-26.49-29.81-29.33c-4.21-0.74-9.25-1.02-15.14-0.83
                                        c-19.81,0.63-34.6,10.15-38.74,30.3c-0.05,0.25,0.11,0.5,0.36,0.56c0.01,0,0.02,0,0.03,0l22.16,3.19c0.26,0.04,0.5-0.14,0.54-0.39
                                        c0-0.02,0-0.03,0-0.05c0.51-7.9,4.59-12.8,12.23-14.69c1.97-0.49,5.25-0.7,9.82-0.63c10.68,0.17,17.25,7.09,17.95,17.51
                                        c0.2,2.97,0.24,4.8,0.11,5.49c-0.06,0.32-0.33,0.56-0.66,0.57c-0.67,0.02-9.94,0.88-27.81,2.57c-3.65,0.34-7.66,0.99-12.02,1.94
                                        c-15.39,3.37-27.06,13.13-26.82,29.88c0.19,12.77,5.74,21.9,16.65,27.4c7.44,3.75,15.15,5.31,23.13,4.68
                                        c10.89-0.85,20.64-6.78,27.39-15.12c0.09-0.11,0.25-0.13,0.37-0.04c0.05,0.04,0.09,0.11,0.09,0.18l1.19,11.36
                                        C721.61,187.19,721.83,187.38,722.08,187.38"/>
                                    <path class="st9" d="M461.41,84.48c0-0.22-0.17-0.39-0.39-0.39h-20.68c-0.22,0-0.39,0.17-0.39,0.39v0v102.66
                                        c0,0.22,0.17,0.39,0.39,0.39l0,0h20.68c0.22,0,0.39-0.17,0.39-0.39V84.48"/>
                                    <path class="st9" d="M482.98,84.71v17.63c0,0.2,0.16,0.37,0.36,0.37c0,0,0,0,0,0h51.64c0.31,0,0.57,0.26,0.57,0.57
                                        c0,0.13-0.04,0.25-0.12,0.35c-18.17,23.41-34.32,44.36-48.45,62.85c-2.55,3.33-4.32,7.13-3.98,11.65c0,0.05,0,3.04-0.01,8.96
                                        c0,0.29,0.24,0.53,0.53,0.53h79.91c0.26,0,0.47-0.21,0.47-0.48l0,0v-17.66c0-0.3-0.25-0.55-0.55-0.55c0,0,0,0,0,0h-52.21
                                        c-0.3,0-0.54-0.24-0.54-0.54c0-0.04,0-0.07,0.01-0.11c0.08-0.42,0.36-0.94,0.84-1.56c25.48-32.95,41.21-53.39,47.2-61.32
                                        c4.75-6.3,3.81-11.95,3.7-20.68c-0.01-0.36-0.29-0.64-0.65-0.64h-78.09C483.26,84.08,482.98,84.36,482.98,84.71L482.98,84.71"/>
                                    <path class="st4" d="M70.04,34.43c-6.26,0.21-11.16,5.52-10.95,11.86l0,0c0.21,6.34,5.46,11.32,11.71,11.11c0,0,0,0,0,0
                                        c6.26-0.21,11.16-5.52,10.95-11.86c0,0,0,0,0,0C81.54,39.19,76.3,34.22,70.04,34.43C70.04,34.43,70.04,34.43,70.04,34.43"/>
                                    <path class="st9" d="M720.01,141.35c-0.06-0.47-0.48-0.8-0.95-0.74c0,0-0.01,0-0.01,0c-6.27,0.87-12.04,1.52-17.31,1.97
                                        c-9.41,0.8-25.11,1.33-25.75,14.4c-0.54,11.12,8.06,15.83,18.15,15.18c12.53-0.82,24.47-7.77,25.73-21.15
                                        C720.25,146.96,720.3,143.74,720.01,141.35"/>
                                </g>
                                <path class="st10" d="M94.82,66.11C87.43,70.4,82.3,76.63,79.45,84.8l-3.89-7.03c-0.17-0.3-0.5-0.47-0.84-0.43
                                    c-9.18,1.12-17.02-0.81-23.52-5.79c-22.13-16.96-13.58-52.9,14.6-56.62c16.62-2.19,31.37,7.52,35.34,23.76
                                    C103.47,48.22,101.36,57.36,94.82,66.11z M70.04,34.43c-6.26,0.21-11.16,5.52-10.95,11.86l0,0c0.21,6.34,5.46,11.32,11.71,11.11
                                    c0,0,0,0,0,0c6.26-0.21,11.16-5.52,10.95-11.86c0,0,0,0,0,0C81.54,39.19,76.3,34.22,70.04,34.43
                                    C70.04,34.43,70.04,34.43,70.04,34.43z"/>
                                <path class="st11" d="M269.57,21.76c-3.36-1.33-5.81-1.84-7.36-1.53c-5.53,1.1-13.6,4.01-24.21,8.73
                                    c-4.26,1.89-9.11,5.02-14.56,9.38c-0.11,0.09-0.12,0.24-0.03,0.36c0.03,0.03,0.06,0.06,0.09,0.07l5.19,2.28
                                    c-12.32-1.09-22.17-1.3-29.56-0.65c-2.73,0.25-4.87,1.94-6.4,5.07c-3.93,8.03-8.03,16.07-12.31,24.14l6.26-16.26
                                    c0.06-0.16-0.02-0.34-0.18-0.4c-0.06-0.02-0.12-0.03-0.18-0.01c-1.46,0.38-3.31,0.94-4.64,1.77c-3.56,2.22-7.69,4.05-12.39,5.5
                                    c-1.45,0.44-4.33,2.12-8.65,5.05c-0.32,0.21-0.58,0.49-0.78,0.81c-0.8,1.33-1.45,2.51-1.96,3.55
                                    c-6.36,13.11-12.15,25.44-17.38,36.98c-0.25,0.56-0.71,1.85-1.36,3.88c-0.86-1.2-1.1-2.2-0.73-3
                                    c11.24-23.81,22.19-47.29,32.86-70.44c5.19-11.26,13.29-18.46,26.24-18.93c11.48-0.42,24.65,0.35,34.97,0.36
                                    c16.25,0,28.88,0.01,37.91,0.04c0.22,0.01,0.4,0.19,0.39,0.4c0,0.05-0.01,0.1-0.03,0.15L269.57,21.76z"/>
                                <path class="st12" d="M269.57,21.76l-19.43,40.7c0.3-4.37-0.94-7.1-5.88-5.95c-5.23,1.21-10.15,2.16-14.74,2.84
                                    c-0.28,0.04-0.53,0.21-0.67,0.46c-3.46,6.08-6.85,12.74-10.18,19.99c-0.3,0.64-1.29,1.72-2.98,3.23
                                    c4.39-9.17,8.78-18.33,13.18-27.48c1.8-3.74,2.86-6.36,3.19-7.87c0.82-3.8-0.3-6.01-3.37-6.63l-5.19-2.28
                                    c-0.13-0.06-0.19-0.21-0.13-0.34c0.02-0.04,0.04-0.07,0.07-0.09c5.45-4.36,10.3-7.49,14.56-9.38c10.61-4.72,18.68-7.63,24.21-8.73
                                    C263.76,19.92,266.21,20.43,269.57,21.76z"/>
                                <circle class="st13" cx="451.22" cy="54.81" r="12.78"/>
                                <path class="st13" d="M304.26,45.67c-0.09-0.18-0.03-0.4,0.15-0.5c0.05-0.03,0.11-0.04,0.17-0.04h24.24c0.33,0,0.62,0.21,0.72,0.52
                                    l36.48,112.32c0.09,0.31,0.42,0.48,0.72,0.39c0.19-0.06,0.33-0.2,0.39-0.39l36.79-112.25c0.12-0.36,0.46-0.6,0.83-0.6h22.39
                                    c0.1,0,0.18,0.08,0.18,0.18c0,0.02,0,0.03-0.01,0.05l-48.33,141.68c-0.08,0.25-0.32,0.42-0.59,0.42h-24.25
                                    c-0.33,0-0.62-0.2-0.74-0.5c-2.87-7.5-4.78-12.63-5.73-15.39c-15.47-44.99-29.6-86.09-42.41-123.32
                                    C304.8,46.91,304.47,46.05,304.26,45.67z"/>
                                <path class="st12" d="M180.42,69.61l-29.35,61.31c-0.09,0.2-0.32,0.28-0.52,0.19c-0.08-0.04-0.14-0.1-0.18-0.17l-11.21-20.46
                                    c0.65-2.03,1.11-3.32,1.36-3.88c5.23-11.54,11.02-23.87,17.38-36.98c0.51-1.04,1.16-2.22,1.96-3.55c0.2-0.32,0.46-0.6,0.78-0.81
                                    c4.32-2.93,7.2-4.61,8.65-5.05c4.7-1.45,8.83-3.28,12.39-5.5c1.33-0.83,3.18-1.39,4.64-1.77c0.17-0.04,0.33,0.07,0.37,0.23
                                    c0.01,0.06,0.01,0.12-0.01,0.18L180.42,69.61z"/>
                                <path class="st14" d="M250.14,62.46l-33.74,70.58l4.69-12.88c0.17-0.47-0.07-0.98-0.55-1.16c-0.09-0.03-0.19-0.05-0.29-0.05
                                    c-3.15-0.05-5.98,0.33-8.49,1.15c-3.15,1.03-6.99,2.74-11.52,5.15c-1.96,1.04-3.85,3.61-5.67,7.71c-0.05,0.11-0.17,0.16-0.28,0.12
                                    h-0.01c-0.15-0.06-0.22-0.23-0.17-0.37c0-0.01,0-0.01,0.01-0.02l3.72-8.14c0.05-0.12,0-0.25-0.12-0.3
                                    c-0.04-0.02-0.09-0.02-0.13-0.02l-1.16,0.22l19.26-41.42c1.69-1.51,2.68-2.59,2.98-3.23c3.33-7.25,6.72-13.91,10.18-19.99
                                    c0.14-0.25,0.39-0.42,0.67-0.46c4.59-0.68,9.51-1.63,14.74-2.84C249.2,55.36,250.44,58.09,250.14,62.46z"/>
                                <path class="st11" d="M94.82,66.11l23.01,43.65l0.56,3.3c0,0.02-0.02,0.03-0.03,0.03c-0.01,0-0.01,0-0.02-0.01l-10.05-18.33
                                    c-0.15-0.28-0.5-0.38-0.77-0.23c-0.01,0-0.02,0.01-0.03,0.02c-3.85,2.33-7.97,5.27-12.36,8.84c-0.85,0.7-1.54,1.38-2.07,2.03
                                    c-0.41,0.49-0.52,1.17-0.28,1.77l4.42,11.52L79.45,84.8C82.3,76.63,87.43,70.4,94.82,66.11z"/>
                                <path class="st13" d="M603.21,96.22c0.01,0.06,0.06,0.1,0.12,0.1c0.03,0,0.05-0.02,0.07-0.05c4.19-6.61,10.08-10.94,17.67-13.01
                                    c5.7-1.55,12.58-1.14,18.37-0.77c0.37,0.02,0.65,0.32,0.65,0.69v19.24c0,0.25-0.21,0.46-0.47,0.46c-0.01,0-0.02,0-0.03,0
                                    c-7.48-0.5-14.12-0.46-21.37,2.09c-7.22,2.55-11.91,7.51-14.07,14.9c-1.13,3.87-1,8.01-1.01,12.3c-0.02,17.47-0.02,35.81,0,55
                                    c0,0.22-0.18,0.4-0.4,0.4h0h-20.2c-0.48,0-0.87-0.39-0.87-0.88V84.44c0-0.2,0.16-0.36,0.36-0.36h0h19.52c0.22,0,0.4,0.16,0.42,0.38
                                    L603.21,96.22z"/>
                                <path class="st13" d="M722.08,187.38c-0.25,0-0.47-0.19-0.5-0.45l-1.19-11.36c-0.01-0.14-0.14-0.25-0.28-0.23
                                    c-0.07,0.01-0.13,0.04-0.18,0.09c-6.75,8.34-16.5,14.27-27.39,15.12c-7.98,0.63-15.69-0.93-23.13-4.68
                                    c-10.91-5.5-16.46-14.63-16.65-27.4c-0.24-16.75,11.43-26.51,26.82-29.88c4.36-0.95,8.37-1.6,12.02-1.94
                                    c17.87-1.69,27.14-2.55,27.81-2.57c0.33-0.01,0.6-0.25,0.66-0.57c0.13-0.69,0.09-2.52-0.11-5.49c-0.7-10.42-7.27-17.34-17.95-17.51
                                    c-4.57-0.07-7.85,0.14-9.82,0.63c-7.64,1.89-11.72,6.79-12.23,14.69c-0.01,0.26-0.23,0.46-0.49,0.44c-0.02,0-0.03,0-0.05,0
                                    l-22.16-3.19c-0.26-0.04-0.43-0.28-0.39-0.53c0-0.01,0-0.02,0-0.03c4.14-20.15,18.93-29.67,38.74-30.3
                                    c5.89-0.19,10.93,0.09,15.14,0.83c16.06,2.84,27.36,12.34,29.81,29.33c0.53,3.65,0.8,7.11,0.81,10.39
                                    c0.09,28.19,0.09,49.58,0.01,64.17c0,0.24-0.19,0.44-0.43,0.44H722.08z M720.01,141.35c-0.06-0.47-0.48-0.8-0.95-0.74
                                    c0,0-0.01,0-0.01,0c-6.27,0.87-12.04,1.52-17.31,1.97c-9.41,0.8-25.11,1.33-25.75,14.4c-0.54,11.12,8.06,15.83,18.15,15.18
                                    c12.53-0.82,24.47-7.77,25.73-21.15C720.25,146.96,720.3,143.74,720.01,141.35z"/>
                                <path class="st13" d="M440.34,84.09h20.68c0.22,0,0.39,0.17,0.39,0.39v102.66c0,0.22-0.17,0.39-0.39,0.39h-20.68
                                    c-0.22,0-0.39-0.17-0.39-0.39V84.48C439.95,84.26,440.12,84.09,440.34,84.09z"/>
                                <path class="st13" d="M483.61,84.08h78.09c0.36,0,0.64,0.28,0.65,0.64c0.11,8.73,1.05,14.38-3.7,20.68
                                    c-5.99,7.93-21.72,28.37-47.2,61.32c-0.48,0.62-0.76,1.14-0.84,1.56c-0.06,0.29,0.13,0.58,0.42,0.64c0.04,0.01,0.07,0.01,0.11,0.01
                                    h52.21c0.3,0,0.55,0.25,0.55,0.55c0,0,0,0,0,0v17.66c0,0.27-0.21,0.48-0.47,0.48l0,0h-79.91c-0.29,0-0.53-0.24-0.53-0.53
                                    c0.01-5.92,0.01-8.91,0.01-8.96c-0.34-4.52,1.43-8.32,3.98-11.65c14.13-18.49,30.28-39.44,48.45-62.85c0.19-0.25,0.15-0.61-0.1-0.8
                                    c-0.1-0.08-0.22-0.12-0.35-0.12h-51.64c-0.2,0-0.36-0.17-0.36-0.37v0V84.71C482.98,84.36,483.26,84.08,483.61,84.08L483.61,84.08z"
                                    />
                                <path class="st12" d="M117.83,109.76l5.68,10.7c-0.99-0.19-1.82-0.68-2.49-1.47c-0.26-0.31-0.69-0.38-1.03-0.18l-15.64,9.03
                                    c-0.32,0.18-0.44,0.57-0.28,0.9c2.42,4.92,5.71,9.97,6.59,15.34L97.2,118.7l-4.42-11.52c-0.24-0.6-0.13-1.28,0.28-1.77
                                    c0.53-0.65,1.22-1.33,2.07-2.03c4.39-3.57,8.51-6.51,12.36-8.84c0.27-0.17,0.62-0.08,0.78,0.18c0.01,0.01,0.01,0.02,0.02,0.03
                                    l10.05,18.33c0.01,0.01,0.03,0.01,0.04,0c0,0,0.01-0.01,0.01-0.02L117.83,109.76z"/>
                                <path class="st14" d="M123.51,120.46l25.26,50.07c-2.31-2.23-4.22-4.78-5.72-7.65c-0.14-0.26-0.39-0.45-0.69-0.49l-0.52-0.08
                                    c-0.21-0.03-0.41,0.12-0.45,0.34c0,0.01,0,0.02,0,0.03c-0.01,0.18-0.09,0.34-0.23,0.48c-4.59,4.52-9.05,9.11-13.39,13.76
                                    l-17.11-32.84c-0.88-5.37-4.17-10.42-6.59-15.34c-0.16-0.33-0.04-0.72,0.28-0.9l15.64-9.03c0.34-0.2,0.77-0.13,1.03,0.18
                                    C121.69,119.78,122.52,120.27,123.51,120.46z"/>
                                <path class="st15" d="M216.4,133.04c-9.23,19.83-18.56,39.64-28,59.43c-2.88,6.04-6.04,12.12-9.79,17.39
                                    c-2.92,4.09-7.63,6.57-12.97,7.25c-10.58,1.35-18.42-2.89-23.53-12.71c-4.77-9.15-9.55-18.31-14.34-27.48
                                    c4.34-4.65,8.8-9.24,13.39-13.76c0.14-0.14,0.22-0.3,0.23-0.48c0.01-0.22,0.2-0.39,0.42-0.37c0.01,0,0.02,0,0.03,0l0.52,0.08
                                    c0.3,0.04,0.55,0.23,0.69,0.49c1.5,2.87,3.41,5.42,5.72,7.65c1.95,3.67,3.92,7.35,5.9,11.04c1.71,3.17,6.37,6.44,10.15,4.18
                                    c2.27-1.35,3.89-2.96,4.88-4.81c1.19-2.23,5.01-10.31,11.44-24.22c4.99-10.81,10.09-21.56,15.29-32.27l1.16-0.22
                                    c0.13-0.02,0.24,0.06,0.27,0.19c0.01,0.05,0,0.09-0.02,0.13l-3.72,8.14c-0.07,0.15,0,0.32,0.14,0.38c0,0,0.01,0,0.02,0.01h0.01
                                    c0.11,0.04,0.23-0.01,0.28-0.12c1.82-4.1,3.71-6.67,5.67-7.71c4.53-2.41,8.37-4.12,11.52-5.15c2.51-0.82,5.34-1.2,8.49-1.15
                                    c0.5,0.01,0.9,0.42,0.89,0.92c0,0.1-0.02,0.2-0.05,0.29L216.4,133.04z"/>
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-white translate-y-[2px] -translate-x-2">ADK</span>
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <nav class="hidden md:flex space-x-2">
                        <a href="{{ route('vizra.dashboard') }}"
                           class="flex items-center space-x-2 px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('vizra.dashboard') ? 'bg-gray-800/50 text-white' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            <span>Dashboard</span>
                        </a>

                        <a href="{{ route('vizra.chat') }}"
                           class="flex items-center space-x-2 px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('vizra.chat') ? 'bg-gray-800/50 text-white' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <span>Chat Interface</span>
                        </a>

                        <a href="{{ route('vizra.eval-runner') }}"
                           class="flex items-center space-x-2 px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('vizra.eval-runner') ? 'bg-gray-800/50 text-white' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <span>Eval Runner</span>
                        </a>

                    </nav>
                </div>

                <!-- Right side actions -->
                <div class="flex items-center space-x-4">
                    <a href="https://vizra.ai"
                       target="_blank"
                       class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                    </a>
                    <a href="https://github.com/vizra-ai/vizra-adk"
                       target="_blank"
                       class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0C5.374 0 0 5.373 0 12 0 17.302 3.438 21.8 8.207 23.387c.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden">
            @yield('content')
        </main>
    </div>

    <!-- Livewire Scripts -->
    @livewireScripts

    <!-- Modal Container for JSON Viewer -->
    <div id="json-modal-container"></div>

    <!-- JSON Viewer Scripts -->
    <script>
        // JSON Viewer functionality
        window.jsonViewerInitialized = window.jsonViewerInitialized || false;
        
        if (!window.jsonViewerInitialized) {
            window.jsonViewerInitialized = true;
            
            // Copy JSON to clipboard
            window.copyJsonToClipboard = function(elementId) {
                const element = document.getElementById(elementId);
                const text = element.textContent || element.innerText;
                
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => {
                        showCopyFeedback(elementId);
                    }).catch(err => {
                        console.error('Failed to copy: ', err);
                        fallbackCopyToClipboard(text);
                    });
                } else {
                    fallbackCopyToClipboard(text);
                }
            };
            
            // Fallback copy method
            function fallbackCopyToClipboard(text) {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    showCopyFeedback();
                } catch (err) {
                    console.error('Fallback copy failed: ', err);
                }
                
                document.body.removeChild(textArea);
            }
            
            // Show copy feedback
            function showCopyFeedback(elementId) {
                // Create temporary feedback element
                const feedback = document.createElement('div');
                feedback.textContent = 'Copied!';
                feedback.className = 'fixed top-4 right-4 bg-green-600 text-white px-3 py-2 rounded shadow-lg z-50 text-sm';
                document.body.appendChild(feedback);
                
                setTimeout(() => {
                    if (document.body.contains(feedback)) {
                        document.body.removeChild(feedback);
                    }
                }, 2000);
            }
            
            // Toggle JSON collapse
            window.toggleJsonCollapse = function(elementId) {
                const content = document.getElementById(elementId);
                const chevron = document.getElementById('chevron-' + elementId);
                
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    if (chevron) chevron.style.transform = 'rotate(0deg)';
                } else {
                    content.style.display = 'none';
                    if (chevron) chevron.style.transform = 'rotate(-90deg)';
                }
            };
            
            // Open JSON modal
            window.openJsonModal = function(modalId) {
                console.log('Opening modal:', modalId);
                const modal = document.getElementById(modalId);
                if (modal) {
                    console.log('Modal found:', modal);
                    
                    // Move modal to body to escape any parent constraints
                    const modalContainer = document.getElementById('json-modal-container');
                    if (modalContainer) {
                        modalContainer.appendChild(modal);
                    } else {
                        document.body.appendChild(modal);
                    }
                    
                    // Show the modal
                    modal.classList.remove('hidden');
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                    
                    // Apply syntax highlighting if Prism is available
                    if (window.Prism) {
                        Prism.highlightAllUnder(modal);
                    }
                } else {
                    console.error('Modal not found:', modalId);
                }
            };
            
            // Close JSON modal
            window.closeJsonModal = function(modalId) {
                console.log('Closing modal:', modalId);
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                } else {
                    console.error('Modal not found for closing:', modalId);
                }
            };
            
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('[id^="modal-"]');
                    modals.forEach(modal => {
                        if (!modal.classList.contains('hidden')) {
                            modal.classList.add('hidden');
                            modal.style.display = 'none';
                            document.body.style.overflow = 'auto';
                        }
                    });
                }
            });
            
            // Apply syntax highlighting if Prism is available
            document.addEventListener('DOMContentLoaded', function() {
                if (window.Prism) {
                    Prism.highlightAll();
                }
            });
            
            // Re-initialize after Livewire updates
            document.addEventListener('livewire:load', function() {
                console.log('Livewire loaded - JSON viewer ready');
            });
            
            document.addEventListener('livewire:update', function() {
                console.log('Livewire updated - JSON viewer ready');
                // Re-apply syntax highlighting for new content
                if (window.Prism) {
                    Prism.highlightAll();
                }
            });
        }
    </script>

    @stack('scripts')
</body>
</html>
