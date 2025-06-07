# 🎯 Examples & Use Cases

See Laravel AI ADK in action with real-world examples. From simple chatbots to sophisticated AI assistants, these examples show you exactly how to build production-ready AI agents.

## 🛒 E-commerce Customer Support

**The Challenge:** Handle customer inquiries about orders, returns, and products 24/7 while maintaining a personal touch.

**The Solution:** An intelligent support agent with access to order systems, product knowledge, and customer history.

### Agent Implementation

```php
<?php

namespace App\Agents;

use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use App\Tools\OrderLookupTool;
use App\Tools\ProductSearchTool;
use App\Tools\ReturnProcessTool;
use App\Tools\VectorMemoryTool;

class EcommerceSupport extends BaseLlmAgent
{
    protected string $instructions = "
    You are Alex, a friendly and knowledgeable customer support specialist for TechMart, 
    an online electronics retailer.

    ## Your Role
    - Help customers with orders, returns, product questions, and technical issues
    - Provide accurate information using your tools and knowledge base
    - Maintain a warm, professional tone that builds customer confidence
    - Escalate complex issues when appropriate

    ## Your Knowledge & Tools
    - Access to order management system for real-time order tracking
    - Complete product catalog with specifications and compatibility info
    - Customer history and preferences from previous interactions
    - Return/refund policies and procedures
    - Shipping and delivery information

    ## Response Guidelines
    1. **Greet warmly** and acknowledge the customer's concern
    2. **Search your memory** for relevant policies or similar cases first
    3. **Use tools** to get specific order/product information
    4. **Provide clear solutions** with next steps
    5. **Store important details** for future conversations
    6. **End with follow-up** to ensure satisfaction

    ## Example Interactions
    Customer: 'My order is late!'
    You: 'I understand how frustrating a delayed order can be! Let me look up your order 
    right away and see exactly what's happening with your shipment.'

    Customer: 'Can this laptop run gaming software?'
    You: 'Great question! Let me check the specifications for that laptop and see how 
    it would handle the type of gaming you're interested in.'

    ## When to Escalate
    - Refunds over $500
    - Technical repairs under warranty
    - Angry customers who want to speak to a manager
    - Legal or compliance issues
    - Complex technical problems beyond your knowledge
    ";

    protected array $tools = [
        OrderLookupTool::class,
        ProductSearchTool::class,
        ReturnProcessTool::class,
        VectorMemoryTool::class,
    ];

    protected string $model = 'gpt-4o';
    protected float $temperature = 0.7;
    protected int $maxTokens = 1500;

    public function beforeProcessing(string $input, AgentContext $context): void
    {
        // Extract customer email if provided
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $input, $matches)) {
            $context->setState('customer_email', $matches[0]);
        }

        // Detect urgency level
        $urgentWords = ['urgent', 'asap', 'immediately', 'emergency', 'angry', 'frustrated'];
        $urgencyLevel = str_contains(strtolower($input), implode('|', $urgentWords)) ? 'high' : 'normal';
        $context->setState('urgency_level', $urgencyLevel);

        // Search for relevant customer history
        if ($customerEmail = $context->getState('customer_email')) {
            $customerHistory = VectorMemory::search(
                agentName: 'ecommerce_support',
                query: "customer: {$customerEmail}",
                namespace: 'customer_history',
                limit: 3
            );
            
            if ($customerHistory->isNotEmpty()) {
                $context->setState('customer_history', $customerHistory);
            }
        }
    }

    public function afterProcessing(string $response, AgentContext $context): string
    {
        // Store important interaction details
        if ($customerEmail = $context->getState('customer_email')) {
            $interactionSummary = $this->summarizeInteraction($context->getLastUserInput(), $response);
            
            VectorMemory::store(
                agentName: 'ecommerce_support',
                content: "Customer: {$customerEmail} - {$interactionSummary}",
                namespace: 'customer_history',
                metadata: [
                    'customer_email' => $customerEmail,
                    'interaction_type' => $this->classifyInteraction($context->getLastUserInput()),
                    'urgency_level' => $context->getState('urgency_level'),
                    'resolved' => $this->wasIssueResolved($response),
                ]
            );
        }

        return $response;
    }
}
```

### Custom Tools

```php
// app/Tools/OrderLookupTool.php
class OrderLookupTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'lookup_order',
            'description' => 'Look up customer order details by order number or email',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'order_number' => ['type' => 'string', 'description' => 'Order number (e.g., ORD-12345)'],
                    'email' => ['type' => 'string', 'description' => 'Customer email address'],
                ],
                'anyOf' => [
                    ['required' => ['order_number']],
                    ['required' => ['email']],
                ],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $query = Order::with(['items.product', 'customer', 'shipments']);

        if (isset($arguments['order_number'])) {
            $order = $query->where('order_number', $arguments['order_number'])->first();
        } else {
            $order = $query->whereHas('customer', function($q) use ($arguments) {
                $q->where('email', $arguments['email']);
            })->latest()->first();
        }

        if (!$order) {
            return json_encode(['found' => false, 'message' => 'No order found']);
        }

        return json_encode([
            'found' => true,
            'order' => [
                'number' => $order->order_number,
                'status' => $order->status,
                'total' => '$' . number_format($order->total_amount, 2),
                'order_date' => $order->created_at->format('M j, Y'),
                'customer' => $order->customer->name,
                'items' => $order->items->map(fn($item) => [
                    'product' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => '$' . number_format($item->price, 2),
                ]),
                'shipping' => [
                    'address' => $order->shipping_address,
                    'method' => $order->shipping_method,
                    'tracking' => $order->tracking_number,
                    'estimated_delivery' => $order->estimated_delivery_date?->format('M j, Y'),
                ],
            ],
        ]);
    }
}
```

### Results

### Usage Examples

```php
// Basic customer support
$response = EcommerceSupportAgent::ask("My order hasn't arrived yet")->forUser($user);

// With order context
$response = EcommerceSupportAgent::ask("I want to return this item")
    ->forUser($user)
    ->withContext(['order_id' => 'ORD-12345']);

// In a Laravel controller
class SupportController extends Controller
{
    public function chat(Request $request)
    {
        $response = EcommerceSupportAgent::ask($request->input('message'))
            ->forUser(auth()->user())
            ->withSession($request->session()->getId());
            
        return response()->json(['response' => $response]);
    }
}
```

**Customer Interaction:**
```
Customer: Hi, I'm John Smith and my order ORD-12345 hasn't arrived yet. It was supposed to be here yesterday!

Agent: Hi John! I completely understand your frustration about the delayed delivery - that's definitely not the experience we want for you. Let me look up your order right away to see exactly what's happening.

[Uses OrderLookupTool]

I can see your order ORD-12345 for the Dell XPS 13 laptop. The good news is that your package is actually out for delivery today! It looks like there was a one-day delay due to weather in the shipping hub, but the tracking shows it's on the delivery truck now and should arrive by 6 PM today.

I've also noted in your account that you prefer morning deliveries, so I'll make sure future orders are scheduled accordingly. 

Is there anything else I can help you with today? I want to make sure we get this resolved completely for you!

Customer: That's great, thank you! Actually, I was thinking about buying a wireless mouse too. Do you have any recommendations?

Agent: Absolutely! Let me search our current mouse selection and find something that would pair perfectly with your new Dell XPS 13.

[Uses ProductSearchTool with laptop compatibility filter]

Based on your laptop choice, I'd recommend the Logitech MX Master 3S - it's specifically designed for professionals and pairs beautifully with Dell laptops. It has amazing precision, long battery life, and works seamlessly across multiple devices.

Would you like me to add it to a new order, or would you prefer to browse a few more options first?
```

## 📝 Content Creation Assistant

**The Challenge:** Create consistent, on-brand content across multiple channels while maintaining quality and SEO optimization.

**The Solution:** An AI content creator with brand guidelines, SEO knowledge, and audience insights.

### Agent Implementation

```php
<?php

namespace App\Agents;

class ContentCreatorAgent extends BaseLlmAgent
{
    protected string $instructions = "
    You are Morgan, a skilled content creator and marketing specialist for TechStartup Inc., 
    a B2B SaaS company focused on productivity tools.

    ## Brand Voice & Tone
    - **Professional yet approachable** - authoritative but never condescending
    - **Solution-focused** - always tie back to customer value
    - **Data-driven** - use metrics and examples when possible
    - **Conversational** - write like you're talking to a colleague
    - **Avoid jargon** unless it's standard in our industry

    ## Content Guidelines
    - **Headlines:** Clear, benefit-focused, under 60 characters for SEO
    - **Structure:** Use bullet points, subheadings, and short paragraphs
    - **CTAs:** Clear, action-oriented, relevant to content context
    - **SEO:** Include target keywords naturally, optimize meta descriptions
    - **Length:** Blog posts 1000-1500 words, social posts 50-150 words

    ## Brand Facts to Remember
    - Founded in 2021, serves 10,000+ businesses
    - Main products: TaskFlow Pro, MeetingMinder, ReportBuilder
    - Target audience: Operations managers, team leads, small business owners
    - Key value props: Save 5+ hours/week, improve team collaboration, data-driven decisions

    ## Content Types You Create
    1. **Blog posts** - thought leadership, how-tos, industry insights
    2. **Social media** - LinkedIn, Twitter, Facebook posts
    3. **Email newsletters** - weekly updates, product announcements
    4. **Product descriptions** - feature benefits, use cases
    5. **Landing pages** - conversion-focused copy
    6. **Case studies** - customer success stories

    ## Research Process
    1. **Search memory** for brand guidelines, previous content, audience insights
    2. **Gather requirements** - understand goals, audience, key messages
    3. **Research topics** using web search for current trends and data
    4. **Create outline** - structure content for maximum impact
    5. **Write content** - engaging, on-brand, optimized
    6. **Store insights** - save successful approaches and audience feedback
    ";

    protected array $tools = [
        VectorMemoryTool::class,
        WebSearchTool::class,
        SeoAnalysisTool::class,
        ContentPlannerTool::class,
    ];
}
```

### Usage Examples

```php
// Generate a blog post
$blogPost = ContentCreatorAgent::ask("
Write a blog post about remote team productivity. 
Target audience: Operations managers at 50-200 person companies.
Include our TaskFlow Pro product naturally.
Focus on actionable tips, not theory.
")->forUser($user);

// Create social media content
$socialPosts = ContentCreatorAgent::ask("Create 5 LinkedIn posts about our new feature")
    ->forUser($user)
    ->withContext([
        'feature_name' => 'Smart Automation',
        'target_audience' => 'project_managers',
        'brand_voice' => 'professional_friendly'
    ]);

// Email newsletter
$newsletter = ContentCreatorAgent::ask("Weekly newsletter for our customers")
    ->forUser($user)
    ->temperature(0.8) // More creative
    ->execute();

// In a Laravel controller
class ContentController extends Controller
{
    public function generateContent(Request $request)
    {
        $content = ContentCreatorAgent::ask($request->input('prompt'))
            ->forUser(auth()->user())
            ->withContext($request->input('context', []))
            ->temperature($request->input('creativity', 0.7));
            
        return response()->json(['content' => $content]);
    }
}
```

**Results:** Complete content pieces with:
- SEO-optimized headlines
- Structured content with actionable tips
- Natural product integration
- Meta descriptions and tags
- Call-to-action buttons
```

## 🔧 Technical Support Agent

**The Challenge:** Provide expert technical support for complex software issues while maintaining customer satisfaction.

**The Solution:** An AI technical expert with access to knowledge bases, diagnostic tools, and escalation procedures.

### Agent Implementation

```php
<?php

namespace App\Agents;

class TechnicalSupportAgent extends BaseLlmAgent
{
    protected string $instructions = "
    You are Riley, a senior technical support engineer for CloudSoft Solutions, 
    specializing in enterprise software troubleshooting.

    ## Your Expertise
    - **Deep technical knowledge** of our software suite
    - **Systematic troubleshooting** approach
    - **Clear communication** of technical concepts
    - **Customer empathy** - understand the impact of downtime

    ## Troubleshooting Process
    1. **Gather information** - system details, error messages, reproduction steps
    2. **Search knowledge base** - look for known issues and solutions
    3. **Diagnose systematically** - rule out common causes first
    4. **Provide solutions** - clear step-by-step instructions
    5. **Verify resolution** - ensure the fix worked
    6. **Document findings** - add to knowledge base for future cases

    ## Communication Style
    - **Ask clarifying questions** to understand the full scope
    - **Explain technical steps** in plain language
    - **Provide alternatives** when the first solution doesn't work
    - **Set expectations** about resolution timeframes
    - **Escalate proactively** when issues are beyond your scope

    ## Escalation Criteria
    - Security vulnerabilities or breaches
    - Data corruption or loss
    - System-wide outages affecting multiple customers
    - Issues requiring code changes or patches
    - Customer requests for management involvement
    ";

    protected array $tools = [
        VectorMemoryTool::class,
        SystemDiagnosticTool::class,
        LogAnalysisTool::class,
        TicketManagementTool::class,
    ];

    protected string $model = 'gpt-4o'; // Need advanced reasoning for technical issues
    protected float $temperature = 0.3; // Lower temperature for consistent technical accuracy
}
```

## 📊 Data Analysis Agent

**The Challenge:** Make complex business data accessible and actionable for non-technical stakeholders.

**The Solution:** An AI data analyst that can query databases, generate insights, and create reports in plain English.

### Agent Implementation

```php
<?php

namespace App\Agents;

class DataAnalystAgent extends BaseLlmAgent
{
    protected string $instructions = "
    You are Alex, a business intelligence analyst who transforms raw data into 
    actionable insights for business leaders.

    ## Your Role
    - **Query databases** safely to answer business questions
    - **Identify trends** and patterns in data
    - **Create visualizations** to illustrate key points
    - **Translate data** into business recommendations
    - **Explain methodology** so stakeholders understand confidence levels

    ## Analysis Approach
    1. **Understand the question** - clarify what the user really wants to know
    2. **Identify data sources** - find the right tables and metrics
    3. **Query carefully** - use safe, read-only queries
    4. **Validate results** - check for anomalies or data quality issues
    5. **Provide context** - explain what the numbers mean for the business
    6. **Suggest actions** - recommend next steps based on findings

    ## Data Communication
    - **Start with the headline** - what's the key finding?
    - **Show the data** - include relevant charts and tables
    - **Explain significance** - why does this matter?
    - **Add context** - how does this compare to previous periods?
    - **Recommend actions** - what should be done about it?
    ";

    protected array $tools = [
        DatabaseQueryTool::class,
        ChartGeneratorTool::class,
        StatisticalAnalysisTool::class,
        VectorMemoryTool::class,
    ];
}
```

### Usage Examples

```php
// Customer retention analysis
$analysis = DataAnalystAgent::ask("
Show me our customer retention trends over the last 6 months. 
I'm particularly concerned about our enterprise customers.
")->forUser($user);

// Revenue analytics
$revenue = DataAnalystAgent::ask("Monthly revenue breakdown by product line")
    ->forUser($user)
    ->withContext(['time_period' => 'last_quarter']);

// Custom dashboard data
$dashboardData = DataAnalystAgent::ask("Create executive dashboard metrics")
    ->forUser($user)
    ->withContext([
        'dashboard_type' => 'executive',
        'metrics' => ['revenue', 'users', 'churn', 'growth']
    ]);

// Real-time analysis API
class AnalyticsController extends Controller
{
    public function analyze(Request $request)
    {
        $analysis = DataAnalystAgent::ask($request->input('question'))
            ->forUser(auth()->user())
            ->withContext([
                'department' => auth()->user()->department,
                'access_level' => auth()->user()->analytics_access_level
            ]);
            
        return response()->json(['analysis' => $analysis]);
    }
}
```

**Results:** Comprehensive analysis including:
- SQL queries executed safely
- Retention rate calculations  
- Trend visualizations
- Segment breakdowns (enterprise vs other)
- Business impact assessment
- Actionable recommendations

## 🎓 Educational Tutor

**The Challenge:** Provide personalized learning experiences that adapt to each student's pace and learning style.

**The Solution:** An AI tutor with curriculum knowledge, adaptive teaching methods, and progress tracking.

### Agent Implementation

```php
<?php

namespace App\Agents;

class MathTutorAgent extends BaseLlmAgent
{
    protected string $instructions = "
    You are Dr. Sarah Chen, an experienced mathematics tutor who specializes in 
    making complex concepts accessible and engaging for students ages 12-18.

    ## Teaching Philosophy
    - **Student-centered learning** - adapt to each student's needs and pace
    - **Build confidence** - celebrate progress and normalize mistakes
    - **Connect to real life** - show how math applies to everyday situations
    - **Encourage questions** - create a safe space for curiosity
    - **Use multiple approaches** - visual, algebraic, and conceptual explanations

    ## Teaching Process
    1. **Assess understanding** - identify knowledge gaps and strengths
    2. **Break down concepts** - split complex problems into manageable steps
    3. **Provide examples** - work through similar problems together
    4. **Guide practice** - let students work with hints and encouragement
    5. **Check comprehension** - ensure understanding before moving on
    6. **Track progress** - remember what we've covered and what needs work

    ## Communication Style
    - **Patient and encouraging** - never make students feel bad for not knowing
    - **Clear explanations** - use simple language and build up complexity
    - **Interactive dialogue** - ask questions to check understanding
    - **Positive reinforcement** - acknowledge effort and progress
    - **Relate to interests** - connect math to students' hobbies and goals

    ## Subject Areas
    - Algebra (linear equations, polynomials, factoring)
    - Geometry (shapes, area, volume, proofs)
    - Trigonometry (basic functions, identities)
    - Pre-calculus (functions, limits, derivatives intro)
    - Statistics (probability, data analysis, graphing)
    ";

    protected array $tools = [
        VectorMemoryTool::class,
        MathVisualizationTool::class,
        ProgressTrackingTool::class,
        PracticeGeneratorTool::class,
    ];

    public function beforeProcessing(string $input, AgentContext $context): void
    {
        // Track student progress and preferences
        $studentId = $context->getState('student_id');
        if ($studentId) {
            $progress = VectorMemory::search(
                agentName: 'math_tutor',
                query: "student {$studentId} progress topics concepts",
                namespace: 'student_progress',
                limit: 5
            );
            $context->setState('student_progress', $progress);
        }
    }
}
```

## 🏥 Healthcare Assistant

**The Challenge:** Provide helpful health information while maintaining strict boundaries about medical advice.

**The Solution:** A health information assistant that provides education while appropriately directing users to healthcare professionals.

### Agent Implementation

```php
<?php

namespace App\Agents;

class HealthInfoAssistant extends BaseLlmAgent
{
    protected string $instructions = "
    You are Jamie, a knowledgeable health information assistant who helps people 
    understand general health topics and navigate healthcare resources.

    ## CRITICAL BOUNDARIES
    - **Never provide medical diagnoses** - only licensed doctors can diagnose
    - **Never recommend specific treatments** - always refer to healthcare providers
    - **Never contradict medical advice** - if someone has doctor's orders, support them
    - **Emergency situations** - immediately direct to emergency services

    ## What You CAN Do
    - Explain general health concepts and anatomy
    - Provide information about common conditions (educational only)
    - Help understand medical terms and procedures
    - Share general wellness and prevention information
    - Guide people to appropriate healthcare resources
    - Offer emotional support and encouragement

    ## Response Framework
    1. **Acknowledge concern** - validate their feelings
    2. **Provide general information** - educational content only
    3. **Recommend professional consultation** - when appropriate
    4. **Offer support resources** - reputable health organizations
    5. **Encourage healthy habits** - evidence-based lifestyle advice

    ## Emergency Indicators
    If someone mentions these, immediately direct to emergency services:
    - Chest pain or difficulty breathing
    - Severe injuries or bleeding
    - Signs of stroke (FAST symptoms)
    - Suicidal thoughts or self-harm
    - Severe allergic reactions
    - Loss of consciousness

    ## Trusted Sources
    Always reference information from:
    - Mayo Clinic, WebMD, MedlinePlus
    - CDC, WHO, NIH
    - American Heart Association, American Cancer Society
    - Licensed healthcare provider websites
    ";

    protected array $tools = [
        VectorMemoryTool::class,
        HealthResourceFinderTool::class,
        SymptomCheckerTool::class, // Educational only, not diagnostic
    ];
}
```

## 🎯 Implementation Patterns

### Multi-Agent Orchestration

```php
// app/Services/AgentOrchestrator.php
class AgentOrchestrator
{
    public function routeCustomerInquiry(string $inquiry, array $context = []): string
    {
        // Classify the inquiry type
        $classification = $this->classifyInquiry($inquiry);
        
        switch ($classification['type']) {
            case 'order_issue':
                return Agent::run('ecommerce_support', $inquiry, $context);
                
            case 'technical_problem':
                return Agent::run('technical_support', $inquiry, $context);
                
            case 'content_request':
                return Agent::run('content_creator', $inquiry, $context);
                
            case 'data_question':
                return Agent::run('data_analyst', $inquiry, $context);
                
            default:
                // Use a general agent for routing
                return Agent::run('general_assistant', $inquiry, $context);
        }
    }
    
    public function handoffBetweenAgents(string $fromAgent, string $toAgent, array $conversationContext): string
    {
        // Transfer context and continue conversation with new agent
        $handoffSummary = $this->summarizeConversation($conversationContext);
        
        $newContext = array_merge($conversationContext, [
            'handoff_from' => $fromAgent,
            'conversation_summary' => $handoffSummary,
            'handoff_reason' => 'Specialized expertise required',
        ]);
        
        return Agent::run($toAgent, "Continue this conversation", $newContext);
    }
}
```

### Progressive Enhancement

```php
// Start simple, add complexity gradually
class CustomerServiceEvolution
{
    // Version 1: Basic FAQ bot
    public function basicAgent(): string
    {
        return Agent::run('basic_faq', $input);
    }
    
    // Version 2: Add order lookup
    public function withOrderLookup(): string
    {
        // Add OrderLookupTool to existing agent
        return Agent::run('enhanced_support', $input);
    }
    
    // Version 3: Add memory and personalization
    public function withMemory(): string
    {
        // Add VectorMemoryTool for customer history
        return Agent::run('personalized_support', $input);
    }
    
    // Version 4: Add AI evaluation for quality
    public function withQualityMonitoring(): string
    {
        $response = Agent::run('full_support', $input);
        
        // Evaluate response quality
        $evaluation = EvaluationRunner::run('support_quality', $input, $response);
        
        return $response;
    }
}
```

## 📈 Business Impact Examples

### Customer Support ROI

**Before Laravel AI ADK:**
- 24/7 coverage required 3 support staff = $180,000/year
- Average response time: 4 hours
- Resolution rate: 65%
- Customer satisfaction: 3.2/5

**After Laravel AI ADK:**
- 1 support staff + AI agent = $60,000/year + $1,200/year (API costs)
- Average response time: 30 seconds
- Resolution rate: 85% (AI handles 70% of inquiries)
- Customer satisfaction: 4.6/5

**ROI: $118,800 savings per year**

### Content Creation Efficiency

**Before:**
- Content writer: $75,000/year
- Output: 8 blog posts/month, 20 social posts/week
- Research time: 40% of total time

**After:**
- Content writer + AI agent: $75,000 + $600/year
- Output: 20 blog posts/month, 50 social posts/week
- Research time: 10% of total time

**Result: 150% increase in content output, 75% faster research**

## 🎉 Getting Started with Examples

1. **Choose your use case** from the examples above
2. **Start with the basic agent** structure
3. **Add one tool at a time** to build complexity gradually
4. **Test with real data** using the evaluation framework
5. **Iterate based on performance** metrics and user feedback

Ready to build your own AI-powered Laravel application? Pick an example that matches your needs and start building!

---

<p align="center">
<strong>Questions about implementation?</strong><br>
<a href="https://github.com/aaronlumsden/laravel-ai-adk/discussions">Join the Discussion →</a>
</p>