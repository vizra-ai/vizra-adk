<?php

namespace Vizra\VizraADK\Http\Controllers;

use Illuminate\Routing\Controller;

class WebController extends Controller
{
    /**
     * Show the dashboard page
     */
    public function dashboard()
    {
        return view('vizra-adk::dashboard');
    }

    /**
     * Show the chat interface
     */
    public function chat()
    {
        return view('vizra-adk::chat');
    }

    /**
     * Show the evaluation runner
     */
    public function eval()
    {
        return view('vizra-adk::eval-runner');
    }
}
