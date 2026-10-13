<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;


/**
 * Launch theme demo for the fictional Relay uptime monitoring SaaS.
 */
class LaunchDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Relay is built by a small, remote team of engineers who have been on call for a long time. Meet the people behind Relay.',
        'blog' => 'Guides on uptime monitoring, on-call, incident response and status pages from the Relay team.',
        'changelog' => 'New features, improvements and fixes in Relay, the uptime monitoring and incident alerting platform for engineering teams.',
        'contact' => 'Talk to the Relay team about Enterprise plans, security reviews, migrations from other tools or anything else.',
        'customers' => 'How 4,000 engineering teams use Relay to catch outages early, page the right person and keep their users informed.',
        'features' => 'Uptime and API monitoring from 18 regions, on-call schedules, alerts by Slack, SMS and phone, and hosted status pages in one tool.',
        'imprint' => 'Legal notice of Relay Labs, Inc.',
        'incident-postmortems' => 'How to write blameless incident postmortems your team actually reads, with a template you can copy.',
        'on-call-without-burnout' => 'Seven changes that make on-call rotations fairer and quieter without missing real incidents.',
        'pricing' => 'Simple pricing for Relay: start free with 10 monitors, upgrade to Pro or Team when you need more checks, people and status pages.',
        'privacy' => 'Privacy policy of Relay Labs, Inc.',
        'signup' => 'Create your free Relay account in less than a minute. No credit card required.',
        'status-pages' => 'What makes a good status page during an outage: components, wording, update cadence and subscriber notifications.',
        'welcome' => 'Your Relay plan is active. Here is how to set up your first monitors, alerts and status page.',
    ];

    /**
     * Curated Unsplash photos used by the SaaS demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const PHOTOS = [
        'dashboard' => ['photo-1551288049-bebda4e38f71', 'Monitoring dashboard', 'A laptop showing dashboards with charts and key metrics'],
        'meeting' => ['photo-1521737711867-e3b97375f902', 'Incident review', 'A team discussing around a wooden table with laptops and notebooks'],
        'office' => ['photo-1497366754035-f200968a6e72', 'Relay office', 'A bright office with a long table, glass meeting rooms and plants'],
        'planning' => ['photo-1552664730-d307ca884978', 'Planning session', 'A team planning in front of a wall of sticky notes'],
        'servers' => ['photo-1558494949-ef010cbdcc31', 'Server room', 'Rows of servers with blue status lights in a data centre'],
        'workspace' => ['photo-1516321318423-f06f85e504b3', 'Engineering workspace', 'A modern workspace with connected screens and a laptop'],
        'person-1' => ['photo-1580489944761-15a19d654956', 'Maya Okafor', 'Portrait of a smiling woman with dark hair in a bun', 'faces'],
        'person-2' => ['photo-1507003211169-0a1dd7228f2d', 'Daniel Brooks', 'Portrait of a smiling man with short dark hair and a beard', 'faces'],
        'person-3' => ['photo-1494790108377-be9c29b29330', 'Sofia Lindqvist', 'Portrait of a smiling woman with long curly hair', 'faces'],
        'person-4' => ['photo-1500648767791-00dcc994a43e', 'Arjun Mehta', 'Portrait of a man with short hair and a light beard', 'faces'],
        'person-5' => ['photo-1438761681033-6461ffad8d80', 'Lea Fischer', 'Portrait of a woman with brown hair in front of a light wall', 'faces'],
        'person-6' => ['photo-1472099645785-5658abf4ff4e', 'Tom Walsh', 'Portrait of a smiling man in a suit jacket', 'faces'],
        'team-1' => ['photo-1534528741775-53994a69daeb', 'Nora Castillo', 'Portrait of a woman with dark hair looking into the camera', 'faces'],
        'team-2' => ['photo-1506794778202-cad84cf45f1d', 'Felix Wagner', 'Portrait of a man with dark hair and a beard', 'faces'],
        'team-3' => ['photo-1544005313-94ddf0286df2', 'Priya Raman', 'Portrait of a woman with long dark hair', 'faces'],
        'team-4' => ['photo-1519085360753-af0119f7cbe7', 'Sam Okoye', 'Portrait of a smiling man in a white shirt', 'faces'],
    ];

    private string $blogId;
    /** @var array<string, string> */
    private array $brands = [];
    private string $element;
    /** @var array<string, string> */
    private array $icons = [];
    private string $logoFile;
    /** @var array<string, string> */
    private array $shots = [];


    /**
     * Creates the about page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAbout( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'About',
            'title' => 'About Relay: Built by Engineers Who Have Been On Call',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'We build the tool we *wished we had at 3 am*',
                'subtitle' => 'About Relay',
                'text' => 'Relay started in 2021 after one too many outages that our users noticed before we did. Today a team of 34 people in eleven countries keeps watch over 2 million checks a day.',
                'buttons' => [
                    ['label' => 'Join the team', 'url' => '/contact'],
                    ['label' => 'Read the changelog', 'url' => '/changelog'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '2021', 'text' => 'Founded in Berlin and Toronto'],
                    ['title' => '34', 'text' => 'People in eleven countries'],
                    ['title' => '4,000+', 'text' => 'Teams rely on Relay every day'],
                    ['title' => '100%', 'text' => 'Remote and profitable since 2024'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'office' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Calm tools for *stressful moments*\n\nIncidents are stressful enough. We design Relay so that the right person gets one clear alert, knows what is broken and can tell users what's going on in a few clicks.\n\n- **Fewer alerts:** confirmation from three regions before anyone is paged\n- **Clear context:** response times, errors and recent deploys in one place\n- **Honest updates:** status pages your users actually trust",
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Our story so far',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '2021', 'title' => 'A side project', 'text' => 'Nora and Felix build a small uptime checker for their own projects and share it with a few friends.'],
                    ['label' => '2022', 'title' => 'On-call and alerts', 'text' => 'Schedules, escalations and phone calls turn the checker into a tool for whole teams.'],
                    ['label' => '2023', 'title' => 'Status pages', 'text' => 'Hosted status pages launch and 1,000 teams sign up in the first month.'],
                    ['label' => '2025', 'title' => 'Relay 2.0', 'text' => 'Checks from 18 regions, API monitoring and SOC 2 Type II certification.'],
                    ['label' => '2026', 'title' => 'Relay 3.0', 'text' => 'AI incident summaries and status pages that write their own first draft.'],
                ],
            ]],
            $this->team(),
            $this->cta(),
        ], $home );

        return $this;
    }


    /**
     * Creates the blog page and its posts below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addBlog( Page $home ) : static
    {
        $blog = $this->page( [
            'id' => $this->blogId,
            'lang' => 'en',
            'name' => 'Blog',
            'title' => 'Relay Blog: Uptime, On-Call and Incident Response',
            'path' => 'blog',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'title' => 'Notes on keeping things up and running',
                'level' => '1',
            ]],
            ['id' => 'blog-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->blogId, 'label' => 'Blog'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );

        $this->post( $blog, [
            'name' => 'On-call without burnout',
            'title' => 'On-Call Without Burnout: Seven Changes That Make Rotations Fair',
            'path' => 'on-call-without-burnout',
        ], 'On-call without burnout',
            "Most on-call pain doesn't come from real outages. It comes from noisy alerts, unclear ownership and rotations that always land on the same three people.\n\nWe looked at the schedules and alert history of 600 teams on Relay. The teams that got paged least shared seven habits, and none of them needed a bigger team or a new tool to start.",
            'meeting',
            [
                ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                    'layout' => 'figures',
                    'columns' => '3',
                    'cards' => [
                        ['title' => '−72%', 'text' => 'Fewer pages after confirming alerts from three regions'],
                        ['title' => '1 week', 'text' => 'Rotation length most teams settle on'],
                        ['title' => '15 min', 'text' => 'Escalation delay before the backup is paged'],
                    ],
                ]],
                ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                    'title' => 'The seven changes',
                ]],
                ...$this->section( 'Page only for what users notice', "If a check can fail without anyone noticing, it should create a ticket, not a phone call. Split your monitors into **urgent** and **next business day** and route them differently. A full disk on a build server is annoying. A checkout that returns errors is an emergency.\n\nA good test: would you call a colleague at 3 am for this? If the honest answer is no, the alert shouldn't do it either." ),
                ...$this->section( 'Confirm before you page', "A single failed request from one region is usually the network, not your app. Relay waits for a second region to confirm before it wakes anyone up. In our data, this one setting removes almost three out of four night-time pages, while real outages are still reported within a minute." ),
                ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                    'title' => 'Write down who owns what',
                    'level' => '2',
                ]],
                ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                    'file' => ['id' => $this->shot( 'alerts' ), 'type' => 'file'],
                    'position' => 'grid-end',
                    'ratio' => '1-1',
                    'text' => "Every monitor should belong to a team, and every team needs a schedule. When an alert has no owner, it ends up with whoever is most worried, and that's usually the person who is already on call far too often.\n\n- **One owner per monitor:** a team, not a person\n- **One schedule per team:** with a clear backup\n- **One place to look:** who is on call now and next",
                ]],
                ...$this->section( 'Rotate weekly and hand over on purpose', "One week is long enough to build context and short enough to recover. Hand over on a weekday morning, not on Friday evening, and spend ten minutes on open incidents, flaky monitors and planned deploys.\n\nThis is what a fair rotation for a team of five looks like:" ),
                ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                    'header' => 'row',
                    'table' => [
                        ['Week', 'Primary', 'Backup', 'Handover'],
                        ['Oct 5', 'Maya', 'Daniel', 'Monday 10:00'],
                        ['Oct 12', 'Daniel', 'Lea', 'Monday 10:00'],
                        ['Oct 19', 'Lea', 'Arjun', 'Monday 10:00'],
                        ['Oct 26', 'Arjun', 'Tom', 'Monday 10:00'],
                        ['Nov 2', 'Tom', 'Maya', 'Monday 10:00'],
                    ],
                ]],
                ...$this->section( 'Escalate slowly, but always', "Give the primary 15 minutes to acknowledge before the backup is paged, then another 15 minutes before the manager gets a call. Shorter delays turn every alert into a team event. Longer ones leave real outages unanswered." ),
                ...$this->section( 'Pay for nights and weekends', "Time off in lieu or a flat on-call allowance makes rotations fairer and keeps experienced people in the schedule. Teams that pay for on-call have fewer people asking to be taken off the rotation." ),
                ...$this->section( 'Review your alerts every month', "Once a month, look at every alert that paged someone. Delete the ones nobody acted on, tune the ones that fired too early and add the ones you missed. Relay's alert report shows the noisiest monitors at the top." ),
                ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                    'items' => [
                        ['text' => 'We went from 40 night-time pages a month to six. The only thing we changed was confirming alerts and deleting what nobody acted on.', 'name' => 'Lea Fischer', 'role' => 'Engineering Manager, Paperplane', 'file' => ['id' => $this->img( 'person-5' ), 'type' => 'file']],
                    ],
                ]],
                ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                    'title' => 'Questions from readers',
                    'items' => [
                        ['title' => 'How many people do we need for a rotation?', 'text' => 'At least four, so nobody is on call more than one week a month. With fewer people, share a rotation with another team.'],
                        ['title' => 'Should managers be on call?', 'text' => 'Yes, as the second escalation level. It keeps them close to the alert quality their team deals with.'],
                        ['title' => 'What about follow-the-sun?', 'text' => 'If your team spans time zones, hand over at the end of each working day. Relay supports up to three handovers a day.'],
                    ],
                ]],
            ],
        );

        $this->post( $blog, [
            'name' => 'Better status pages',
            'title' => 'What Makes a Good Status Page During an Outage',
            'path' => 'status-pages',
        ], 'What makes a good status page during an outage',
            "During an outage, your status page gets more visitors than your homepage. It's the one place where users decide whether to trust you or to open a support ticket.\n\nWe looked at 1,200 public incidents on Relay status pages and found four patterns that cut support tickets by half. None of them need a big team, just a few decisions made before the next outage.",
            'dashboard',
            [
                ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                    'layout' => 'figures',
                    'columns' => '3',
                    'cards' => [
                        ['title' => '−48%', 'text' => 'Support tickets with an update in the first 10 minutes'],
                        ['title' => '30 min', 'text' => 'Longest gap between updates users accept'],
                        ['title' => '6', 'text' => 'Components are enough for most products'],
                    ],
                ]],
                ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                    'text' => "## Post early, even if you don't know much\n\n\"We are investigating reports of slow page loads\" is a perfect first update. Silence is what makes users nervous. The teams with the fewest tickets posted their first update a median of six minutes after the first alert, long before they knew the cause.\n\nWrite the first update for your users, not for your engineers. Say what people notice, not which service failed.",
                ]],
                ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                    'file' => ['id' => $this->shot( 'status' ), 'type' => 'file'],
                    'position' => 'grid-start',
                    'ratio' => '1-1',
                    'text' => "## Name components your users recognise\n\nUsers know the **dashboard**, **API** and **checkout**. They don't know your Kubernetes clusters. Group internal services below the parts of the product people actually use and keep the list short.\n\n- **Website** and **dashboard** for everyone\n- **API** for developers and integrations\n- **Checkout** or **payments** if you sell online",
                ]],
                ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                    'title' => 'Keep a steady rhythm',
                    'level' => '2',
                ]],
                ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                    'text' => 'Promise the next update and keep the promise, even if nothing changed. Relay reminds the incident owner when an update is due. This is how a well-run incident looked on the status page of one of our customers:',
                ]],
                ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                    'layout' => 'vertical',
                    'items' => [
                        ['label' => '14:06', 'title' => 'Investigating', 'text' => 'Some customers see errors when paying by card. We are looking into it and will update you within 30 minutes.'],
                        ['label' => '14:21', 'title' => 'Identified', 'text' => 'A configuration change at our payment provider is rejecting some cards. We are switching to our backup provider.'],
                        ['label' => '14:48', 'title' => 'Monitoring', 'text' => 'Payments work again for all cards. We are watching the error rate closely.'],
                        ['label' => '15:20', 'title' => 'Resolved', 'text' => 'Card payments were unavailable for some customers for 42 minutes. No payment was charged twice. We will publish a full report this week.'],
                    ],
                ]],
                ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                    'text' => "## Close with a short summary\n\nWhat happened, how long it lasted, who was affected and what you are changing. Link the full postmortem when it's ready. Users forgive outages much more easily than they forgive being left in the dark.\n\n## Prepare your wording before you need it\n\nNobody writes well at 3 am. Save templates for the four states in Relay and you only have to fill in the details during an incident. Relay 3.0 even suggests the first update from the failing checks.",
                ]],
                ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                    'title' => 'Questions from readers',
                    'items' => [
                        ['title' => 'Should we show uptime percentages?', 'text' => 'Yes, for the last 90 days. Hiding them makes people assume the worst.'],
                        ['title' => 'Can we use our own domain?', 'text' => 'On the Pro and Team plans you can use status.yourdomain.com with an automatic TLS certificate.'],
                        ['title' => 'Do we need private status pages?', 'text' => 'For internal tools and B2B customers with dedicated environments, yes. They are included in the Team plan.'],
                    ],
                ]],
            ],
        );

        $this->post( $blog, [
            'name' => 'Blameless postmortems',
            'title' => 'Blameless Incident Postmortems Your Team Actually Reads',
            'path' => 'incident-postmortems',
        ], 'Blameless postmortems your team actually reads',
            "A postmortem is not a report for management. It's how your team learns from an outage so the same thing doesn't happen again.\n\nThis is the template we use at Relay. It fits on one page, takes about an hour to write and is read by everyone in engineering, not just the people who were paged.",
            'planning',
            [
                ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                    'layout' => 'figures',
                    'columns' => '3',
                    'cards' => [
                        ['title' => '1 page', 'text' => 'Is enough for most incidents'],
                        ['title' => '5 days', 'text' => 'Write it while memories are fresh'],
                        ['title' => '3', 'text' => 'Action items at most, each with an owner'],
                    ],
                ]],
                ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                    'text' => "## Why blameless matters\n\nIf people expect to be blamed, they leave out the details that matter most: the shortcut they took, the alert they ignored, the dashboard they couldn't find. A blameless postmortem assumes everyone acted reasonably with what they knew at the time, and asks why the system made the mistake easy.\n\nThat doesn't mean nobody is responsible. It means the team is responsible for making the same mistake harder next time.",
                ]],
                ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                    'title' => 'From the all-clear to the review',
                    'layout' => 'horizontal',
                    'items' => [
                        ['label' => 'Day 0', 'title' => 'Export the timeline', 'text' => 'Relay saves alerts, pages and status updates of the incident.'],
                        ['label' => 'Day 1–3', 'title' => 'Write the draft', 'text' => 'The incident owner fills in the template with everyone involved.'],
                        ['label' => 'Day 4', 'title' => 'Review together', 'text' => 'A 30 minute meeting to agree on the contributing factors.'],
                        ['label' => 'Day 5', 'title' => 'Share and track', 'text' => 'Publish it internally and add the action items to your backlog.'],
                    ],
                ]],
                ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                    'text' => "## The template\n\nCopy it into your wiki or your repository. Every section has a question it answers, and if a section doesn't apply, leave it out.",
                ]],
                ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                    'language' => 'markdown',
                    'text' => "# Postmortem: <short title>\n\n## Summary\nWhat broke, for how long and who was affected? (2-3 sentences)\n\n## Impact\nUsers affected, failed requests, support tickets, revenue\n\n## Timeline (UTC)\n- 14:02 First alert from checkout-api\n- 14:04 Acknowledged by the on-call engineer\n- 14:06 First status page update\n- 14:31 Rollback of deploy #4812\n- 14:44 All-clear\n\n## Contributing factors\nHow did this make sense at the time?\n\n## What went well\n\n## Action items\n| Action | Owner | Due |\n|--------|-------|-----|",
                ]],
                ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                    'text' => "## Contributing factors, not root causes\n\nAsk \"how did this make sense at the time?\" instead of \"who did this?\". Systems fail in combinations: a missing alert, a risky deploy and a busy Friday afternoon. Looking for the single root cause usually ends at the last person who touched something.\n\n## Action items that get done\n\nPick no more than three changes, give each one an owner and a date, and track them like any other work. A long list of good intentions is the main reason why postmortems stop being read.",
                ]],
                ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                    'title' => 'Example action items',
                    'header' => 'row',
                    'table' => [
                        ['Action', 'Owner', 'Due'],
                        ['Alert when the database pool is 80% full', 'Daniel', 'Oct 20'],
                        ['Run a canary for 10 minutes before full deploys', 'Arjun', 'Oct 31'],
                        ['Add checkout to the public status page', 'Sofia', 'Oct 17'],
                    ],
                ]],
                ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                    'title' => 'Questions from readers',
                    'items' => [
                        ['title' => 'Who should write the postmortem?', 'text' => 'The incident owner, with help from everyone involved. Review it together in a 30 minute meeting.'],
                        ['title' => 'Do small incidents need one?', 'text' => 'Write a short one for every incident that affected users. Skipping the small ones hides patterns.'],
                        ['title' => 'Should we publish them?', 'text' => 'A short public version builds trust. Leave out internal names and security details.'],
                    ],
                ]],
            ],
        );

        return $this;
    }


    /**
     * Creates the changelog page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addChangelog( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Changelog',
            'title' => 'Changelog: New Features and Improvements in Relay',
            'path' => 'changelog',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'title' => 'Changelog',
                'level' => '1',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => 'New features, improvements and fixes, shipped every week. Follow [@relay](https://relay.example) or subscribe to the [Relay blog](/blog) for the bigger stories behind each release.',
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'layout' => 'vertical',
                'items' => [
                    ['label' => 'Oct 7, 2026', 'title' => 'Relay 3.0: AI incident summaries', 'text' => "When an incident starts, Relay now writes a short summary from the failing checks, error responses and recent deploys, and suggests a first status page update.\n\n- Summaries in Slack, Microsoft Teams and the incident timeline\n- One-click status update from the suggested text\n- Works with your own OpenAI, Anthropic or Mistral key on the Team plan"],
                    ['label' => 'Sep 23, 2026', 'title' => 'Heartbeat monitors for cron jobs', 'text' => "Send a ping when a job finishes and Relay alerts you when it's late or missing.\n\n- Grace periods from 1 minute to 7 days\n- `curl` snippets for bash, PHP, Python and Node.js\n- Shows the run duration if you ping at start and end"],
                    ['label' => 'Sep 2, 2026', 'title' => 'Maintenance windows', 'text' => "Plan maintenance in advance, mute alerts for the affected monitors and inform subscribers automatically.\n\n- Recurring windows for weekly deploys\n- Shown on the status page 48 hours in advance"],
                    ['label' => 'Aug 12, 2026', 'title' => 'Faster dashboard and dark mode', 'text' => "The dashboard loads three times faster on accounts with more than 500 monitors and follows your system's light or dark setting.\n\n- **Fixed:** response time chart showed UTC instead of local time\n- **Fixed:** duplicate SMS when two escalation rules matched"],
                    ['label' => 'Jul 21, 2026', 'title' => 'Terraform provider 1.0', 'text' => "Manage monitors, schedules and status pages as code with the official `relay/relay` Terraform provider.\n\n- Import existing resources with `terraform import`\n- Full API coverage, including escalation policies"],
                    ['label' => 'Jun 30, 2026', 'title' => 'Checks from Johannesburg and São Paulo', 'text' => 'Relay now checks your services from 18 regions on six continents, with no extra cost on any plan.'],
                ],
            ]],
            $this->cta(),
        ], $home );

        return $this;
    }


    /**
     * Creates the contact page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addContact( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Contact',
            'title' => 'Contact Relay: Sales, Support and Security',
            'path' => 'contact',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Talk to *a human*',
                'subtitle' => 'Contact',
                'text' => 'Questions about Enterprise plans, security reviews or moving from another tool? Our team answers within one business day.',
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'columns' => '3',
                'cards' => [
                    ['title' => 'Sales', 'file' => $this->icon( 'chat' ), 'text' => "Enterprise plans, volume pricing and invoices.\n\n[sales@relay.example](mailto:sales@relay.example)"],
                    ['title' => 'Support', 'file' => $this->icon( 'life' ), 'text' => "Help with monitors, alerts and integrations.\n\n[support@relay.example](mailto:support@relay.example)"],
                    ['title' => 'Security', 'file' => $this->icon( 'shield' ), 'text' => "Questionnaires, DPA and responsible disclosure.\n\n[security@relay.example](mailto:security@relay.example)"],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Send us a message',
                'description' => 'Tell us a little about your team and what you want to monitor. We usually reply within a few hours.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'company', 'required' => false, 'input' => 'text'],
                    ['field' => 'monitors', 'required' => false, 'input' => 'select', 'options' => "Less than 50\n50 to 250\nMore than 250"],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the customers page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCustomers( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Customers',
            'title' => 'Customers: How Engineering Teams Use Relay',
            'path' => 'customers',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Trusted by teams that *can\'t afford downtime*',
                'subtitle' => 'Customers',
                'text' => 'From two-person startups to platform teams with thousands of services: see how teams use Relay to find problems first and fix them faster.',
                'buttons' => [
                    ['label' => 'Start for free', 'url' => '/signup'],
                    ['label' => 'Talk to sales', 'url' => '/contact'],
                ],
            ]],
            $this->brands(),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Customer stories',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Northwind cut alert noise by 80%', 'file' => ['id' => $this->img( 'workspace' ), 'type' => 'file'], 'text' => "**Fintech · 120 engineers**\nMulti-region confirmation and escalation policies replaced three tools and a lot of 3 am false alarms."],
                    ['title' => 'Kestrel answers outages in 4 minutes', 'file' => ['id' => $this->img( 'meeting' ), 'type' => 'file'], 'text' => "**E-commerce · 45 engineers**\nAI summaries in Slack tell the on-call engineer what broke before they open their laptop."],
                    ['title' => 'Orbital halved its support tickets', 'file' => ['id' => $this->img( 'servers' ), 'type' => 'file'], 'text' => "**Developer platform · 30 engineers**\nA public status page with automatic updates answers customers before they write in."],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '4 min', 'text' => 'Median time to acknowledge an alert'],
                    ['title' => '−80%', 'text' => 'Fewer false alarms on average'],
                    ['title' => '2.1M', 'text' => 'Checks every day from 18 regions'],
                    ['title' => '4.8/5', 'text' => 'Rating from 620 reviews'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Loved by on-call engineers',
                'items' => $this->reviews(),
            ]],
            $this->cta(),
        ], $home );

        return $this;
    }


    /**
     * Creates the features page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addFeatures( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Features',
            'title' => 'Features: Uptime Monitoring, On-Call Alerts and Status Pages',
            'path' => 'features',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Everything you need *when things break*',
                'subtitle' => 'Features',
                'text' => 'Monitoring, alerting, on-call and status pages in one tool, so you don\'t have to glue four products together during an outage.',
                'buttons' => [
                    ['label' => 'Start for free', 'url' => '/signup'],
                    ['label' => 'See pricing', 'url' => '/pricing'],
                ],
                'files' => [['id' => $this->shot( 'dashboard' ), 'type' => 'file']],
            ]],
            $this->features(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->shot( 'alerts' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Alerts that reach *the right person*\n\nRelay knows who is on call, how they want to be reached and who comes next if nobody answers.\n\n- **Schedules:** weekly rotations, overrides and follow-the-sun handovers\n- **Escalations:** Slack first, then SMS, then a phone call\n- **Context:** failing regions, response body and the last deploy in every alert",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->shot( 'status' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Status pages your users *trust*\n\nKeep customers informed while you fix the problem, on your own domain and in your brand.\n\n- **Automatic updates:** components change status when monitors fail\n- **Subscribers:** email, SMS, RSS and webhooks\n- **Private pages:** for internal tools and enterprise customers",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Works with the tools you already use',
                'layout' => 'badges',
                'cards' => [
                    ['title' => 'Slack'],
                    ['title' => 'Microsoft Teams'],
                    ['title' => 'GitHub'],
                    ['title' => 'Terraform'],
                    ['title' => 'Datadog'],
                    ['title' => 'Webhooks'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => 'bash',
                'text' => "# Create a monitor from your CI pipeline\ncurl -X POST https://api.relay.example/v1/monitors \\\n  -H \"Authorization: Bearer \$RELAY_TOKEN\" \\\n  -d url=https://api.example.com/health \\\n  -d interval=30 \\\n  -d regions=eu,us,ap",
            ]],
            $this->cta(),
        ], $home );

        return $this;
    }


    /**
     * Creates the imprint page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addImprint( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Imprint',
            'title' => 'Imprint | Relay',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Relay Labs, Inc.**\n548 Market Street, Suite 0000\nSan Francisco, CA 94104\nUnited States\n\nEmail: hello@relay.example\n\nCEO: Nora Castillo\n\nThis is a demo website for the Launch theme. Relay is a fictional product and the people and companies named are not real.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the pricing page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPricing( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Pricing',
            'title' => 'Pricing: Start Free, Upgrade When You Grow | Relay',
            'path' => 'pricing',
            'type' => 'page',
            'status' => 1,
        ], [
            $this->plans(),
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Compare plans',
                'header' => 'row+col',
                'table' => [
                    ['', 'Hobby', 'Pro', 'Team', 'Enterprise'],
                    ['Monitors', '10', '50', '250', 'Unlimited'],
                    ['Check interval', '3 min', '30 sec', '30 sec', '10 sec'],
                    ['Regions', '3', '18', '18', '18 + private'],
                    ['Users', '1', '5', '25', 'Unlimited'],
                    ['Status pages', '1', '3', '10', 'Unlimited'],
                    ['On-call schedules', '—', '✓', '✓', '✓'],
                    ['SMS and phone alerts', '—', '100 / month', '500 / month', 'Unlimited'],
                    ['AI incident summaries', '—', '—', '✓', '✓'],
                    ['SSO and SCIM', '—', '—', '✓', '✓'],
                    ['Data retention', '30 days', '1 year', '2 years', 'Custom'],
                    ['Support', 'Community', 'Email', 'Priority', 'Dedicated'],
                ],
            ]],
            $this->questions(),
            $this->cta(),
        ], $home );

        return $this;
    }


    /**
     * Creates the privacy page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPrivacy( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Privacy',
            'title' => 'Privacy Policy | Relay',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who we are\n\nRelay Labs, Inc. operates relay.example. You can reach us at privacy@relay.example.\n\n## What we collect\n\nWhen you create an account, we store your name, email address and the settings of your monitors. When you subscribe to a status page, we store your email address or phone number until you unsubscribe.\n\n## Payments\n\nPayments are processed by our payment provider. We never see or store your full card details.\n\n## Your rights\n\nYou can access, export or delete your data at any time in your account settings or by writing to us.\n\nThis is a demo privacy policy for the Launch theme and not legal advice.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the sign up page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addSignup( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Sign up',
            'title' => 'Create Your Free Relay Account',
            'path' => 'signup',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your first monitor is *one minute away*',
                'subtitle' => 'Free forever, no credit card',
                'text' => 'Create an account, paste a URL and Relay starts checking it from three regions right away.',
                'buttons' => [
                    ['label' => 'Create free account', 'url' => '/contact'],
                    ['label' => 'Compare plans', 'url' => '/pricing'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Up and running in three steps',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => '30 seconds', 'title' => 'Add a URL', 'text' => 'Websites, APIs, SSL certificates, DNS records or cron jobs.'],
                    ['label' => '1 minute', 'title' => 'Choose how to be alerted', 'text' => 'Connect Slack, add your phone number or invite your team.'],
                    ['label' => '2 minutes', 'title' => 'Publish a status page', 'text' => 'Pick the components and share the link with your users.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the page shown after a successful checkout below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addWelcome( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Welcome',
            'title' => 'Welcome to Relay',
            'path' => 'welcome',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Thanks, *you\'re all set*',
                'subtitle' => 'Payment received',
                'text' => 'Your plan is active as soon as the payment is confirmed, usually within a few seconds. We have sent the receipt to your email address.',
                'buttons' => [
                    ['label' => 'Read the changelog', 'url' => '/changelog'],
                    ['label' => 'Contact support', 'url' => '/contact'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Next steps',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Invite your team', 'file' => $this->icon( 'users' ), 'text' => 'Add the people who should be on call and set up your first rotation.'],
                    ['title' => 'Connect Slack', 'file' => $this->icon( 'chat' ), 'text' => 'Get alerts and AI summaries in the channel where your team already works.'],
                    ['title' => 'Use your domain', 'file' => $this->icon( 'globe' ), 'text' => 'Point status.yourdomain.com to your status page with one CNAME record.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Returns the shared call to action element.
     *
     * @return array<string, mixed> CTA content element
     */
    protected function cta() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
            'title' => 'Find out about outages *before your users do*',
            'text' => 'Start with 10 free monitors and a status page. Set up takes about two minutes, no credit card required.',
            'buttons' => [
                ['label' => 'Start for free', 'url' => '/signup'],
                ['label' => 'Talk to sales', 'url' => '/contact'],
            ],
        ]];
    }


    /**
     * Creates the shared Relay footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Relay footer', ['columns' => '4', 'cards' => [
            ['title' => 'Relay', 'text' => "Uptime monitoring, on-call alerts and status pages for teams that ship fast.\n\n[Subscribe to product updates](mailto:hello@relay.example?subject=Product%20updates)"],
            ['title' => 'Product', 'text' => "- [Features](/features)\n- [Pricing](/pricing)\n- [Changelog](/changelog)\n- [Sign up](/signup)"],
            ['title' => 'Company', 'text' => "- [About](/about)\n- [Customers](/customers)\n- [Blog](/blog)\n- [Contact](/contact)"],
            ['title' => 'Legal', 'text' => "- [Imprint](/imprint)\n- [Privacy](/privacy)\n- [Security](/contact)"],
        ]] );
    }


    /**
     * Returns the bento grid with the main product features.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function features() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'One tool from the first alert to the all-clear',
            'columns' => '3',
            'cards' => [
                ['title' => 'Checks from 18 regions', 'file' => $this->icon( 'globe' ), 'text' => 'HTTP, keyword, SSL, DNS, TCP and ping checks every 30 seconds. Relay confirms failures from a second region before alerting, so a flaky network never wakes you up.'],
                ['title' => 'API monitoring', 'file' => $this->icon( 'code' ), 'text' => 'Multi-step checks with headers, auth and JSON assertions.'],
                ['title' => 'Cron job heartbeats', 'file' => $this->icon( 'clock' ), 'text' => 'Get alerted when a backup or queue worker stops running.'],
                ['title' => 'On-call that feels fair', 'file' => $this->icon( 'users' ), 'text' => 'Rotations, overrides and escalation policies with alerts by Slack, Teams, SMS, phone call or push. Everybody sees who is on call this week and who comes next.'],
                ['title' => 'AI incident summaries', 'file' => $this->icon( 'sparkles' ), 'text' => 'Know what broke and why in one paragraph, right in Slack.'],
                ['title' => 'Hosted status pages', 'file' => $this->icon( 'signal' ), 'text' => 'On your domain, updated automatically, with subscribers.'],
            ],
        ]];
    }


    /**
     * Returns the ID of the default page image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->shot( 'dashboard' );
    }


    /**
     * Returns the Relay home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Relay'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'launch::product' => [
                'type' => 'launch::product',
                'files' => [],
                'data' => [
                    'name' => 'Relay',
                    'category' => 'DeveloperApplication',
                    'description' => 'Uptime monitoring, on-call alerts and status pages for engineering teams.',
                    'announcement' => 'Relay 3.0 is here: AI incident summaries',
                    'announcement-link' => '/changelog',
                    'status' => 'https://status.relay.example',
                    'price' => '0',
                    'currency' => 'USD',
                    'rating' => '4.8',
                    'reviews' => '620',
                    'notice' => 'SOC 2 Type II certified · GDPR compliant · Data hosted in the EU and US',
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Know about outages *before your users do*',
                'subtitle' => 'New: AI incident summaries in Relay 3.0',
                'text' => 'Relay checks your websites, APIs and cron jobs every 30 seconds from 18 regions, pages the right person and keeps your users informed on a status page.',
                'buttons' => [
                    ['label' => 'Start for free', 'url' => '/signup'],
                    ['label' => 'Book a demo', 'url' => '/contact'],
                ],
                'files' => [['id' => $this->shot( 'dashboard' ), 'type' => 'file']],
                'main' => true,
            ]],
            $this->brands(),
            $this->features(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->shot( 'alerts' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Wake up the right person, *not the whole team*\n\nRelay follows your on-call schedule and escalates step by step until somebody answers. Every alert comes with the failing regions, the response and the last deploy.\n\n- **Fewer false alarms:** confirmed from multiple regions\n- **Every channel:** Slack, Teams, SMS, phone and push\n- **Fair rotations:** overrides and handovers in two clicks",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->shot( 'status' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Status pages that *answer support tickets* for you\n\nWhen a monitor fails, the matching component on your status page changes and subscribers get notified, in your brand and on your domain.\n\n- **Automatic:** no copy and paste during an incident\n- **Subscribers:** email, SMS, RSS, Slack and webhooks\n- **Private pages:** for internal tools and B2B customers",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '99.99%', 'text' => 'Uptime of Relay itself in the last 12 months'],
                    ['title' => '30 s', 'text' => 'Check interval on all paid plans'],
                    ['title' => '4,000+', 'text' => 'Engineering teams rely on Relay'],
                    ['title' => '2.1M', 'text' => 'Checks every day from 18 regions'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Teams sleep better with Relay',
                'items' => $this->reviews(),
            ]],
            $this->plans(),
            $this->questions(),
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the Relay blog',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->blogId, 'label' => 'Blog'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->cta(),
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Relay: uptime and API monitoring from 18 regions, on-call schedules, alerts by Slack, SMS and phone, and hosted status pages for engineering teams. Start free.',
                'keywords' => 'uptime monitoring, website monitoring, API monitoring, on-call, incident alerts, status page, SaaS',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Relay | Uptime Monitoring, On-Call Alerts and Status Pages',
                'description' => 'Know about outages before your users do.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Relay | Uptime Monitoring, On-Call Alerts and Status Pages', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a white line icon once and returns its file reference.
     *
     * @param string $name Icon name: chat, clock, code, globe, life, shield, signal, sparkles or users
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'code' => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/>',
            'life' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M5.6 5.6l4 4M14.4 14.4l4 4M18.4 5.6l-4 4M9.6 14.4l-4 4"/>',
            'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
            'signal' => '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
            'sparkles' => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.5 3.2-5.5 6.5-5.5s5.9 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c2 .8 3.2 2.5 3.5 5.2"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'White line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the Relay SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 40" role="img" aria-labelledby="title desc">
  <title id="title">Relay logo</title>
  <desc id="desc">Indigo to violet rounded square with a white pulse line beside the wordmark Relay</desc>
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366F1"/><stop offset="1" stop-color="#A855F7"/></linearGradient></defs>
  <rect x="2" y="4" width="32" height="32" rx="9" fill="url(#g)"/>
  <path d="M8 21h5l3-7 4 13 3-6h5" fill="none" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
  <text x="44" y="29" fill="#71717A" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="24" font-weight="700" letter-spacing="-1">Relay</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile( $svg, 'relay-logo.svg', 'Relay logo',
                'Indigo to violet rounded square with a white pulse line beside the wordmark Relay', true );
        }

        return $this->logoFile;
    }


    /**
     * Returns the customer logo cloud.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function brands() : array
    {
        $marks = [
            'Northwind' => ['#0EA5E9', '<path d="M18 4l9 28-9-6-9 6z" fill="#0EA5E9"/><path d="M18 4v22l-9 6z" fill="#0284C7"/>'],
            'Kestrel' => ['#F97316', '<path d="M4 26c6-1 10-5 14-14 2 6 6 10 14 10-6 4-14 6-28 4z" fill="#F97316"/><circle cx="24" cy="14" r="2.5" fill="#F97316"/>'],
            'Orbital' => ['#8B5CF6', '<circle cx="18" cy="18" r="7" fill="#8B5CF6"/><ellipse cx="18" cy="18" rx="15" ry="6" fill="none" stroke="#8B5CF6" stroke-width="2.5" transform="rotate(-25 18 18)"/>'],
            'Tidewave' => ['#14B8A6', '<path d="M3 15c5-6 10-6 15 0s10 6 15 0M3 24c5-6 10-6 15 0s10 6 15 0" fill="none" stroke="#14B8A6" stroke-width="3.5" stroke-linecap="round"/>'],
            'Paperplane' => ['#3B82F6', '<path d="M3 17L33 5 25 31 17 22z" fill="#3B82F6"/><path d="M17 22l16-17-12 21z" fill="#1D4ED8"/>'],
            'Quanta' => ['#EC4899', '<path d="M18 3l13 7.5v15L18 33 5 25.5v-15z" fill="none" stroke="#EC4899" stroke-width="3"/><circle cx="18" cy="18" r="4.5" fill="#EC4899"/>'],
        ];

        $cards = [];

        foreach( $marks as $name => [$color, $mark] )
        {
            $width = 48 + strlen( $name ) * 13;
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' 36" role="img"><title>' . $name . '</title>'
                . $mark . '<text x="44" y="26" fill="#71717A" font-family="system-ui, -apple-system, \'Segoe UI\', sans-serif" font-size="22" font-weight="700" letter-spacing="-0.5">' . $name . '</text></svg>';

            $this->brands[$name] ??= $this->svgFile( $svg, strtolower( $name ) . '-logo.svg', $name . ' logo',
                'Logo of ' . $name . ': ' . $color . ' symbol beside the wordmark ' . $name, true );

            $cards[] = ['file' => ['id' => $this->brands[$name], 'type' => 'file']];
        }

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Trusted by 4,000+ engineering teams',
            'layout' => 'badges',
            'cards' => $cards,
        ]];
    }


    /**
     * Creates a Launch demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->ids( $content )[0] ?? $this->file();

        $footer = [
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Relay, uptime monitoring, API monitoring, on-call, incident alerts, status page' );
    }


    /**
     * Builds the Launch demo page tree.
     */
    protected function pages() : void
    {
        $this->blogId = (string) Str::uuid7();
        $home = $this->home();

        $this->addFeatures( $home )
            ->addPricing( $home )
            ->addCustomers( $home )
            ->addChangelog( $home )
            ->addBlog( $home )
            ->addAbout( $home )
            ->addContact( $home )
            ->addSignup( $home )
            ->addWelcome( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the pricing element with monthly and yearly prices.
     *
     * Paid plans start a Cashier checkout if a Pagible Cashier package is installed
     * and the "relay-pro" and "relay-team" roles exist in the Access catalog.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function plans() : array
    {
        $cashier = Route::has( 'cms.cashier' );

        $price = fn( string $id, int $amount, int $interval ) => [
            'id' => $id,
            'kind' => 'subscription',
            'interval' => $interval,
            'currency' => 'USD',
            'reference' => 'price_' . str_replace( '-', '_', $id ),
            'amount' => $amount,
            'label' => '$' . $amount,
            'unit' => $interval === 365 ? 'per year' : 'per month',
        ];

        $paid = fn( string $access ) => $cashier ? ['access' => $access, 'url' => '/welcome'] : ['url' => '/signup'];

        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Simple pricing that *grows with you*',
            'text' => 'Start free and upgrade when you need more monitors or people. Save two months with yearly billing.',
            'items' => [
                [
                    'id' => 'hobby',
                    'name' => 'Hobby',
                    'prices' => [
                        ['id' => 'hobby-month', 'currency' => 'USD', 'amount' => 0, 'label' => '$0', 'unit' => 'per month'],
                        ['id' => 'hobby-year', 'currency' => 'USD', 'amount' => 0, 'label' => '$0', 'unit' => 'per year'],
                    ],
                    'text' => 'For side projects and personal websites.',
                    'features' => "- 10 monitors, every 3 minutes\n- Checks from 3 regions\n- Email and Slack alerts\n- 1 status page",
                    'url' => '/signup',
                    'button' => 'Start for free',
                ],
                [
                    'id' => 'pro',
                    'name' => 'Pro',
                    'prices' => [$price( 'pro-month', 19, 30 ), $price( 'pro-year', 190, 365 )],
                    'text' => 'For small teams running production apps.',
                    'features' => "Everything in Hobby, plus:\n\n- 50 monitors, every 30 seconds\n- Checks from 18 regions\n- On-call schedules and escalations\n- 100 SMS and phone alerts a month\n- 3 status pages on your domain",
                    'button' => 'Start 14-day trial',
                    'highlight' => true,
                    'badge' => 'Most popular',
                ] + $paid( 'relay-pro' ),
                [
                    'id' => 'team',
                    'name' => 'Team',
                    'prices' => [$price( 'team-month', 49, 30 ), $price( 'team-year', 490, 365 )],
                    'text' => 'For growing engineering organisations.',
                    'features' => "Everything in Pro, plus:\n\n- 250 monitors and 25 users\n- AI incident summaries\n- 500 SMS and phone alerts a month\n- 10 public and private status pages\n- SSO, SCIM and audit log",
                    'button' => 'Choose Team',
                ] + $paid( 'relay-team' ),
                [
                    'id' => 'enterprise',
                    'name' => 'Enterprise',
                    'prices' => [['id' => 'enterprise', 'label' => 'Custom']],
                    'text' => 'For large teams with special requirements.',
                    'features' => "Everything in Team, plus:\n\n- Unlimited monitors and users\n- 10 second checks and private regions\n- 99.99% uptime SLA\n- Dedicated support engineer",
                    'url' => '/contact',
                    'button' => 'Talk to sales',
                ],
            ],
        ]];
    }


    /**
     * Creates a post below the blog page.
     *
     * @param Page $parent Blog page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $intro Article introduction
     * @param string $cover PHOTOS key of the cover image
     * @param array<int, array<string, mixed>> $content Content elements of the article body
     * @return Page Created page
     */
    protected function post( Page $parent, array $data, string $title, string $intro, string $cover, array $content ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'blog',
            'status' => 2,
        ], [
            $this->article( $title, $intro, $this->img( $cover ) ),
            ...$content,
            $this->cta(),
        ], $parent );
    }


    /**
     * Returns the frequently asked questions element.
     *
     * @return array<string, mixed> Questions content element
     */
    protected function questions() : array
    {
        return ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
            'title' => 'Frequently asked questions',
            'items' => [
                ['title' => 'Is the Hobby plan really free?', 'text' => 'Yes, forever and without a credit card. It includes 10 monitors, email and Slack alerts and one status page.'],
                ['title' => 'Can I change plans at any time?', 'text' => 'Upgrades apply immediately and are charged pro rata. Downgrades take effect at the end of the billing period.'],
                ['title' => 'What counts as a monitor?', 'text' => 'Every URL, API endpoint, heartbeat, SSL certificate or DNS record that Relay checks. Checks from several regions count as one monitor.'],
                ['title' => 'How do SMS and phone alerts work?', 'text' => 'Paid plans include a monthly allowance. If you need more, additional alerts cost $0.05 each and are billed at the end of the month.'],
                ['title' => 'Where is my data stored?', 'text' => 'You choose between our EU region in Frankfurt and our US region in Virginia when you create your account. Relay is SOC 2 Type II certified and GDPR compliant.'],
                ['title' => 'Do you offer discounts?', 'text' => 'Yearly billing saves two months. Open source projects, non-profits and schools get the Pro plan for free.'],
            ],
        ]];
    }


    /**
     * Returns the customer testimonials.
     *
     * @return array<int, array<string, mixed>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['text' => 'We replaced three tools with Relay and cut our false alarms by 80% in the first month. Our on-call engineers finally sleep through the night again.', 'name' => 'Maya Okafor', 'role' => 'VP Engineering, Northwind', 'file' => ['id' => $this->img( 'person-1' ), 'type' => 'file']],
            ['text' => 'The AI summary in Slack tells me what broke before I have opened my laptop.', 'name' => 'Daniel Brooks', 'role' => 'SRE Lead, Kestrel', 'file' => ['id' => $this->img( 'person-2' ), 'type' => 'file']],
            ['text' => 'Our status page updates itself now. Support tickets during incidents dropped by half.', 'name' => 'Sofia Lindqvist', 'role' => 'Head of Support, Orbital', 'file' => ['id' => $this->img( 'person-3' ), 'type' => 'file']],
            ['text' => 'Setting up monitors with Terraform took one afternoon for 400 services. The API is a joy to work with.', 'name' => 'Arjun Mehta', 'role' => 'Platform Engineer, Tidewave', 'file' => ['id' => $this->img( 'person-4' ), 'type' => 'file']],
            ['text' => 'Fair rotations and overrides in two clicks. Nobody complains about on-call anymore.', 'name' => 'Lea Fischer', 'role' => 'Engineering Manager, Paperplane', 'file' => ['id' => $this->img( 'person-5' ), 'type' => 'file']],
            ['text' => 'Relay caught an expiring SSL certificate three weeks before it would have taken down checkout.', 'name' => 'Tom Walsh', 'role' => 'CTO, Quanta', 'file' => ['id' => $this->img( 'person-6' ), 'type' => 'file']],
        ];
    }


    /**
     * Returns a heading and its text as two content elements.
     *
     * @param string $title Section headline
     * @param string $text Section text as Markdown
     * @return array<int, array<string, mixed>> Heading and text content elements
     */
    protected function section( string $title, string $text ) : array
    {
        return [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $title, 'level' => '2']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $text]],
        ];
    }


    /**
     * Creates (once) a product screenshot as SVG and returns its file ID.
     *
     * @param string $name Screenshot name: alerts, dashboard or status
     * @return string File ID
     */
    protected function shot( string $name ) : string
    {
        if( !isset( $this->shots[$name] ) )
        {
            [$svg, $desc] = match( $name ) {
                'alerts' => [$this->alertsSvg(), 'Relay incident view with an alert for the checkout API, the escalation steps and the AI summary'],
                'status' => [$this->statusSvg(), 'Public status page showing all systems operational with 90 day uptime bars per component'],
                default => [$this->dashboardSvg(), 'Relay dashboard in dark mode with uptime bars, response time chart and a list of monitors'],
            };

            $this->shots[$name] = $this->svgFile( $svg, 'relay-' . $name . '.svg', 'Relay ' . $name, $desc, true );
        }

        return $this->shots[$name];
    }


    /**
     * Returns the team cards.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'The people behind Relay',
            'columns' => '4',
            'cards' => [
                ['title' => 'Nora Castillo', 'file' => ['id' => $this->cropped( 'team-1', 800, 800 ), 'type' => 'file'], 'text' => "**Co-founder & CEO**\nFormer SRE lead, carried a pager for nine years."],
                ['title' => 'Felix Wagner', 'file' => ['id' => $this->cropped( 'team-2', 800, 800 ), 'type' => 'file'], 'text' => "**Co-founder & CTO**\nBuilt the check network that runs in 18 regions."],
                ['title' => 'Priya Raman', 'file' => ['id' => $this->cropped( 'team-3', 800, 800 ), 'type' => 'file'], 'text' => "**Head of Product**\nTalks to five customers every week."],
                ['title' => 'Sam Okoye', 'file' => ['id' => $this->cropped( 'team-4', 800, 800 ), 'type' => 'file'], 'text' => "**Head of Support**\nAnswers most tickets in under an hour."],
            ],
        ]];
    }


    /**
     * Returns the SVG of the incident and alert screen.
     *
     * @return string SVG markup
     */
    private function alertsSvg() : string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif">
  <rect width="800" height="600" rx="18" fill="#0B0B0F"/>
  <rect x="0.5" y="0.5" width="799" height="599" rx="17.5" fill="none" stroke="#27272A"/>
  <circle cx="28" cy="26" r="6" fill="#3F3F46"/><circle cx="48" cy="26" r="6" fill="#3F3F46"/><circle cx="68" cy="26" r="6" fill="#3F3F46"/>
  <rect x="32" y="60" width="736" height="92" rx="12" fill="#1A0F12" stroke="#7F1D1D"/>
  <circle cx="64" cy="106" r="10" fill="#EF4444"/><circle cx="64" cy="106" r="18" fill="none" stroke="#EF444466" stroke-width="2"/>
  <text x="96" y="98" fill="#FAFAFA" font-size="20" font-weight="600">checkout-api is down</text>
  <text x="96" y="124" fill="#A1A1AA" font-size="14">HTTP 503 from Frankfurt, Virginia and Singapore · started 2 min ago</text>
  <rect x="640" y="88" width="104" height="36" rx="18" fill="#FAFAFA"/>
  <text x="692" y="111" fill="#09090B" font-size="14" font-weight="600" text-anchor="middle">Acknowledge</text>
  <rect x="32" y="172" width="356" height="396" rx="12" fill="#111114" stroke="#27272A"/>
  <text x="56" y="208" fill="#FAFAFA" font-size="16" font-weight="600">Escalation</text>
  <circle cx="72" cy="252" r="16" fill="#6366F1"/><text x="72" y="257" fill="#FFFFFF" font-size="12" font-weight="700" text-anchor="middle">MO</text>
  <text x="100" y="248" fill="#FAFAFA" font-size="14" font-weight="600">Maya Okafor</text>
  <text x="100" y="266" fill="#A1A1AA" font-size="12">Slack · notified 14:02</text>
  <rect x="300" y="238" width="64" height="24" rx="12" fill="#22C55E26"/><text x="332" y="255" fill="#4ADE80" font-size="12" text-anchor="middle">Seen</text>
  <line x1="72" y1="270" x2="72" y2="310" stroke="#3F3F46" stroke-width="2"/>
  <circle cx="72" cy="326" r="16" fill="#27272A"/><text x="72" y="331" fill="#A1A1AA" font-size="12" font-weight="700" text-anchor="middle">DB</text>
  <text x="100" y="322" fill="#D4D4D8" font-size="14" font-weight="600">Daniel Brooks</text>
  <text x="100" y="340" fill="#71717A" font-size="12">SMS · in 13 min</text>
  <line x1="72" y1="344" x2="72" y2="384" stroke="#3F3F46" stroke-width="2" stroke-dasharray="4 4"/>
  <circle cx="72" cy="400" r="16" fill="#27272A"/><text x="72" y="405" fill="#A1A1AA" font-size="12" font-weight="700" text-anchor="middle">TW</text>
  <text x="100" y="396" fill="#D4D4D8" font-size="14" font-weight="600">Tom Walsh</text>
  <text x="100" y="414" fill="#71717A" font-size="12">Phone call · in 28 min</text>
  <rect x="56" y="456" width="308" height="88" rx="10" fill="#18181B"/>
  <text x="76" y="486" fill="#A1A1AA" font-size="12">ON CALL THIS WEEK</text>
  <rect x="76" y="500" width="40" height="24" rx="6" fill="#6366F1"/><rect x="120" y="500" width="40" height="24" rx="6" fill="#6366F1"/><rect x="164" y="500" width="40" height="24" rx="6" fill="#6366F1"/><rect x="208" y="500" width="40" height="24" rx="6" fill="#8B5CF6"/><rect x="252" y="500" width="40" height="24" rx="6" fill="#8B5CF6"/><rect x="296" y="500" width="48" height="24" rx="6" fill="#3F3F46"/>
  <rect x="408" y="172" width="360" height="236" rx="12" fill="#111114" stroke="#27272A"/>
  <rect x="428" y="192" width="88" height="24" rx="12" fill="#6366F133"/><text x="472" y="209" fill="#A5B4FC" font-size="12" font-weight="600" text-anchor="middle">AI summary</text>
  <text x="428" y="248" fill="#E4E4E7" font-size="14">Checkout returns 503 since deploy</text>
  <text x="428" y="270" fill="#E4E4E7" font-size="14"><tspan fill="#C4B5FD">#4812</tspan> (14:00). Database pool is</text>
  <text x="428" y="292" fill="#E4E4E7" font-size="14">exhausted, other endpoints are fine.</text>
  <text x="428" y="326" fill="#A1A1AA" font-size="13">Suggested: roll back #4812</text>
  <rect x="428" y="350" width="150" height="36" rx="18" fill="#4F46E5"/><text x="503" y="373" fill="#FFFFFF" font-size="13" font-weight="600" text-anchor="middle">Post status update</text>
  <rect x="408" y="428" width="360" height="140" rx="12" fill="#111114" stroke="#27272A"/>
  <text x="428" y="460" fill="#FAFAFA" font-size="14" font-weight="600">Response time</text>
  <path d="M428 540 L460 534 L492 538 L524 530 L556 536 L588 528 L620 470 L652 482 L684 476 L716 488 L748 480" fill="none" stroke="#818CF8" stroke-width="2.5" stroke-linejoin="round"/>
  <line x1="620" y1="476" x2="620" y2="548" stroke="#EF4444" stroke-dasharray="3 3"/>
</svg>
SVG;
    }


    /**
     * Returns uptime bars as SVG rectangles.
     *
     * @param int $x Left position
     * @param int $y Top position
     * @param int $count Number of bars
     * @param int $seed Variation of the failing bars
     * @param int $width Width of each bar
     * @param int $height Height of each bar
     * @return string SVG markup
     */
    private function bars( int $x, int $y, int $count, int $seed, int $width = 6, int $height = 28 ) : string
    {
        $svg = '';

        for( $i = 0; $i < $count; $i++ )
        {
            $hash = ( $i * 37 + $seed * 101 ) % 53;
            $color = $hash === 0 ? '#EF4444' : ( $hash === 7 || $hash === 29 ? '#F59E0B' : '#22C55E' );
            $svg .= sprintf( '<rect x="%d" y="%d" width="%d" height="%d" rx="2" fill="%s"/>', $x + $i * ( $width + 3 ), $y, $width, $height, $color );
        }

        return $svg;
    }


    /**
     * Returns the SVG of the monitoring dashboard.
     *
     * @return string SVG markup
     */
    private function dashboardSvg() : string
    {
        $rows = '';
        $monitors = [
            ['api.relay.example', 'HTTPS · 30s', '99.99%', '84 ms'],
            ['checkout-api', 'HTTPS · 30s', '99.95%', '212 ms'],
            ['app.relay.example', 'Keyword · 1m', '100%', '128 ms'],
            ['nightly-backup', 'Heartbeat · 24h', '99.72%', '4m 12s'],
            ['db-primary:5432', 'TCP · 30s', '100%', '3 ms'],
        ];

        foreach( $monitors as $idx => [$name, $type, $uptime, $time] )
        {
            $y = 520 + $idx * 72;
            $rows .= sprintf( '<line x1="272" y1="%d" x2="1408" y2="%d" stroke="#1F1F23"/>', $y - 16, $y - 16 )
                . sprintf( '<circle cx="292" cy="%d" r="5" fill="%s"/>', $y + 14, $idx === 3 ? '#F59E0B' : '#22C55E' )
                . sprintf( '<text x="312" y="%d" fill="#FAFAFA" font-size="16" font-weight="600">%s</text>', $y + 10, $name )
                . sprintf( '<text x="312" y="%d" fill="#71717A" font-size="13">%s</text>', $y + 30, $type )
                . $this->bars( 560, $y, 60, $idx + 1 )
                . sprintf( '<text x="1200" y="%d" fill="#E4E4E7" font-size="15" font-weight="600">%s</text>', $y + 20, $uptime )
                . sprintf( '<text x="1300" y="%d" fill="#A1A1AA" font-size="15">%s</text>', $y + 20, $time );
        }

        $stats = '';
        $cards = [['Monitors', '128', '#FAFAFA'], ['Uptime 30 days', '99.98%', '#4ADE80'], ['Avg response', '142 ms', '#FAFAFA'], ['Open incidents', '0', '#FAFAFA']];

        foreach( $cards as $idx => [$label, $value, $color] )
        {
            $x = 272 + $idx * 290;
            $stats .= sprintf( '<rect x="%d" y="132" width="270" height="104" rx="12" fill="#111114" stroke="#27272A"/>', $x )
                . sprintf( '<text x="%d" y="168" fill="#A1A1AA" font-size="14">%s</text>', $x + 22, $label )
                . sprintf( '<text x="%d" y="210" fill="%s" font-size="30" font-weight="700" letter-spacing="-1">%s</text>', $x + 22, $color, $value );
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 900" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif">
  <defs>
    <linearGradient id="area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6366F1" stop-opacity="0.45"/><stop offset="1" stop-color="#6366F1" stop-opacity="0"/></linearGradient>
    <linearGradient id="logo" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366F1"/><stop offset="1" stop-color="#A855F7"/></linearGradient>
  </defs>
  <rect width="1440" height="900" rx="20" fill="#09090B"/>
  <rect x="0" y="0" width="240" height="900" rx="20" fill="#0E0E11"/>
  <rect x="220" y="0" width="20" height="900" fill="#0E0E11"/>
  <line x1="240" y1="0" x2="240" y2="900" stroke="#1F1F23"/>
  <rect x="24" y="28" width="28" height="28" rx="8" fill="url(#logo)"/>
  <path d="M29 43h4l2.5-6 3.5 11 2.5-5h4" fill="none" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
  <text x="64" y="49" fill="#FAFAFA" font-size="18" font-weight="700">Relay</text>
  <rect x="16" y="96" width="208" height="38" rx="8" fill="#1C1C21"/>
  <text x="32" y="121" fill="#FAFAFA" font-size="15" font-weight="500">Monitors</text>
  <text x="32" y="167" fill="#A1A1AA" font-size="15">Incidents</text>
  <text x="32" y="209" fill="#A1A1AA" font-size="15">On-call</text>
  <text x="32" y="251" fill="#A1A1AA" font-size="15">Status pages</text>
  <text x="32" y="293" fill="#A1A1AA" font-size="15">Integrations</text>
  <text x="32" y="335" fill="#A1A1AA" font-size="15">Settings</text>
  <rect x="16" y="796" width="208" height="76" rx="10" fill="#6366F11A" stroke="#6366F14D"/>
  <text x="32" y="826" fill="#C7D2FE" font-size="13" font-weight="600">Relay 3.0</text>
  <text x="32" y="848" fill="#A1A1AA" font-size="12">AI incident summaries</text>
  <text x="272" y="84" fill="#FAFAFA" font-size="28" font-weight="700" letter-spacing="-1">Monitors</text>
  <rect x="1180" y="56" width="228" height="38" rx="19" fill="#22C55E1A" stroke="#22C55E4D"/>
  <circle cx="1202" cy="75" r="5" fill="#22C55E"/>
  <text x="1216" y="80" fill="#4ADE80" font-size="14" font-weight="500">All systems operational</text>
  {$stats}
  <rect x="272" y="260" width="1136" height="200" rx="12" fill="#111114" stroke="#27272A"/>
  <text x="296" y="294" fill="#FAFAFA" font-size="16" font-weight="600">Response time</text>
  <text x="1384" y="294" fill="#71717A" font-size="13" text-anchor="end">Last 24 hours</text>
  <line x1="296" y1="340" x2="1384" y2="340" stroke="#1F1F23"/><line x1="296" y1="380" x2="1384" y2="380" stroke="#1F1F23"/><line x1="296" y1="420" x2="1384" y2="420" stroke="#1F1F23"/>
  <path d="M296 400 C340 392 370 380 410 386 S480 360 530 368 S610 396 660 380 S740 340 790 352 S870 384 920 372 S1000 330 1050 344 S1130 380 1180 364 S1280 350 1330 356 L1384 350 L1384 440 L296 440 Z" fill="url(#area)"/>
  <path d="M296 400 C340 392 370 380 410 386 S480 360 530 368 S610 396 660 380 S740 340 790 352 S870 384 920 372 S1000 330 1050 344 S1130 380 1180 364 S1280 350 1330 356 L1384 350" fill="none" stroke="#818CF8" stroke-width="2.5"/>
  <text x="312" y="490" fill="#71717A" font-size="12" letter-spacing="1">MONITOR</text>
  <text x="560" y="490" fill="#71717A" font-size="12" letter-spacing="1">LAST 60 CHECKS</text>
  <text x="1200" y="490" fill="#71717A" font-size="12" letter-spacing="1">UPTIME</text>
  <text x="1300" y="490" fill="#71717A" font-size="12" letter-spacing="1">RESPONSE</text>
  {$rows}
</svg>
SVG;
    }


    /**
     * Returns the SVG of the public status page.
     *
     * @return string SVG markup
     */
    private function statusSvg() : string
    {
        $rows = '';
        $components = [['Website', '100%'], ['Dashboard', '99.98%'], ['API', '99.95%'], ['Checkout', '99.99%']];

        foreach( $components as $idx => [$name, $uptime] )
        {
            $y = 268 + $idx * 76;
            $rows .= sprintf( '<text x="72" y="%d" fill="#18181B" font-size="15" font-weight="600">%s</text>', $y, $name )
                . sprintf( '<text x="728" y="%d" fill="#16A34A" font-size="13" font-weight="500" text-anchor="end">Operational</text>', $y )
                . $this->bars( 72, $y + 14, 72, $idx + 3, 6, 26 )
                . sprintf( '<text x="72" y="%d" fill="#A1A1AA" font-size="11">90 days ago</text>', $y + 56 )
                . sprintf( '<text x="728" y="%d" fill="#A1A1AA" font-size="11" text-anchor="end">%s uptime</text>', $y + 56, $uptime );
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif">
  <rect width="800" height="600" rx="18" fill="#FAFAFA"/>
  <rect x="0.5" y="0.5" width="799" height="599" rx="17.5" fill="none" stroke="#E4E4E7"/>
  <rect x="40" y="36" width="28" height="28" rx="8" fill="#0EA5E9"/>
  <text x="80" y="57" fill="#09090B" font-size="20" font-weight="700" letter-spacing="-0.5">Orbital status</text>
  <rect x="640" y="34" width="120" height="34" rx="17" fill="#09090B"/>
  <text x="700" y="56" fill="#FFFFFF" font-size="13" font-weight="600" text-anchor="middle">Subscribe</text>
  <rect x="40" y="96" width="720" height="72" rx="12" fill="#16A34A"/>
  <circle cx="76" cy="132" r="12" fill="#FFFFFF33"/>
  <path d="M70 132l4 4 8-8" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
  <text x="100" y="139" fill="#FFFFFF" font-size="20" font-weight="600">All systems operational</text>
  <rect x="40" y="192" width="720" height="368" rx="12" fill="#FFFFFF" stroke="#E4E4E7"/>
  <text x="72" y="226" fill="#71717A" font-size="12" letter-spacing="1">COMPONENTS</text>
  {$rows}
</svg>
SVG;
    }
}
