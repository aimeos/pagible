<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * Default theme demo for the Meridian Works professional-services site.
 *
 * Used for the default theme and as fallback for themes that do not ship their
 * own "\Database\Seeders\<Studly>Demo" provider (see Demo::make()).
 */
class DefaultDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'blog' => 'Field notes from Meridian Works on operating-model design, service improvement, project governance, and durable delivery practices.',
        'decisions-before-deliverables' => 'Why strong consulting engagements settle ownership, constraints, and decision rights before teams begin producing deliverables.',
        'how-to-run-a-steering-meeting-people-can-use' => 'A practical format for steering meetings that resolves decisions, exposes delivery risk, and leaves teams with clear ownership.',
        'the-handover-starts-in-week-one' => 'Build client ownership from the first week of an engagement instead of leaving knowledge transfer until the final presentation.',
        'when-a-project-needs-recovery-not-more-reporting' => 'Recognize when a troubled programme needs a recovery decision rather than another reporting layer, workshop, or revised status deck.',
        'docs' => 'The Meridian Works client handbook explains how engagements begin, how decisions are recorded, and what clients can expect each week.',
        'docs/project-governance' => 'A practical governance guide covering roles, decision records, steering meetings, risks, changes, and project handover.',
    ];

    /**
     * Curated Unsplash photos used across the Meridian Works demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'brief' => ['photo-1450101499163-c8848c66ca85', 'Project brief and decision notes', 'Printed project brief, charts, and handwritten notes arranged for review'],
        'dashboard' => ['photo-1551288049-bebda4e38f71', 'Programme performance review', 'Performance dashboard open on a laptop during a project review'],
        'decisions' => ['photo-1551836022-d5d88e9218df', 'Decision record review', 'Consultant reviewing an approved record and its supporting evidence'],
        'delivery' => ['photo-1497366811353-6870744d04b2', 'Delivery workspace', 'Calm professional workspace prepared for focused client delivery'],
        'governance' => ['photo-1551434678-e076c223a692', 'Governance planning session', 'Team reviewing priorities and responsibilities on a glass wall'],
        'handover' => ['photo-1758873268745-dd2cf0d677b5', 'Working side by side', 'Client and adviser working together at one computer'],
        'hero' => ['photo-1521737711867-e3b97375f902', 'Meridian Works client team', 'Professional team reviewing a business problem around a shared table'],
        'meeting' => ['photo-1552664730-d307ca884978', 'Focused steering meeting', 'Leadership team discussing ownership and delivery decisions'],
        'model' => ['photo-1531403009284-440f080d1e12', 'Operating model workshop', 'Team arranging service and operating-model ideas on a planning board'],
        'systems' => ['photo-1516321318423-f06f85e504b3', 'Connected delivery systems', 'Professional workspace with connected screens used to review service operations'],
        'team' => ['photo-1521737711867-e3b97375f902', 'Client and consulting team', 'Business team reviewing evidence and agreeing the next course of action'],
    ];

    private string $element;
    private string $guideFile;
    private string $logoFile;
    /** @var array<string, string> File IDs for fixed-ratio slideshow images */
    private array $slideImages = [];


    /**
     * Creates the field-notes section below the home page.
     *
     * @param Page $home Home page
     * @param string $blogId Field-notes page ID referenced by listing elements
     * @return static Same object for fluent calls
     */
    protected function addBlog( Page $home, string $blogId ) : static
    {
        $blog = $this->page( [
            'id' => $blogId,
            'lang' => 'en',
            'name' => 'Field notes',
            'title' => 'Field Notes',
            'path' => 'blog',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from the work',
                'subtitle' => 'Meridian Works field notes',
                'text' => 'Practical observations on decisions, delivery, governance, and the habits that make change hold after an advisory team leaves.',
                'files' => [['id' => $this->img( 'delivery' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Recent articles',
                'layout' => 'list',
                'limit' => 4,
                'order' => '_lft',
                'parent-page' => ['value' => $blogId, 'label' => 'Field notes'],
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Decisions before deliverables',
            'title' => 'Decisions Before Deliverables',
            'path' => 'decisions-before-deliverables',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Decisions before deliverables',
                "A polished plan can conceal a weak agreement. The team may have a timeline, a workstream map, and a weekly meeting, yet still lack a shared answer to three basic questions: what must change, who can decide, and which constraint takes priority when the plan meets reality.\n\nGood advisory work settles those questions early. Deliverables then become evidence of decisions, not substitutes for them.",
                $this->img( 'decisions' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Map the decisions first',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'brief' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "Before scheduling workshops, list the decisions the engagement is expected to resolve. Name the owner of each decision, the evidence they need, and the latest useful decision date.\n\nThis simple record exposes false dependencies. It also distinguishes a genuine executive choice from work the delivery team can resolve within its mandate.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'A useful decision record',
                'header' => 'row',
                'table' => [
                    ['Field', 'Question it answers', 'Example'],
                    ['Decision', 'What must be settled?', 'Choose one service owner across all regions'],
                    ['Owner', 'Who has authority?', 'Chief Operating Officer'],
                    ['Evidence', 'What makes the choice defensible?', 'Service cost, risk, and customer impact'],
                    ['Deadline', 'When does delay become expensive?', 'Before the next planning cycle'],
                    ['Consequence', 'What changes once agreed?', 'Regional teams move to one operating cadence'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "A decision log should remain short enough to use in a live meeting. If it becomes a second project plan, it has lost its purpose. Keep supporting analysis elsewhere and link it to the choice it informs.",
            ]],
            $this->articleHero( 'Put the next decision in view', 'Meridian Works helps leadership teams turn broad change mandates into clear choices, ownership, and an executable sequence.' ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'How to run a steering meeting people can use',
            'title' => 'How to Run a Steering Meeting People Can Use',
            'path' => 'how-to-run-a-steering-meeting-people-can-use',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'How to run a steering meeting people can use',
                "A steering group is not an audience for a status presentation. It exists to remove obstacles that the delivery team cannot remove alone. When every update receives equal airtime, the decisions disappear inside the reporting.\n\nA useful meeting gives attention in proportion to consequence. Stable work stays in the written update. The room is reserved for choices, exceptions, and risks that need authority.",
                $this->img( 'meeting' )
            ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three inputs are enough',
                'cards' => [
                    ['title' => 'Decisions required', 'text' => 'State the recommendation, alternatives, evidence, owner, and final decision date.', 'file' => ['id' => $this->img( 'decisions' ), 'type' => 'file']],
                    ['title' => 'Material exceptions', 'text' => 'Report only variance that changes cost, timing, scope, benefit, or exposure.', 'file' => ['id' => $this->img( 'dashboard' ), 'type' => 'file']],
                    ['title' => 'Actions from last time', 'text' => 'Close the loop on previous commitments before adding another list of actions.', 'file' => ['id' => $this->img( 'brief' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Write the record in the room',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Record decisions, conditions, and owners while the people involved are present. Read the wording back before moving on. A decision that needs to be interpreted the following morning was not properly closed.\n\nEnd by naming what the delivery team can now do differently. That is the test of whether the meeting governed the work or merely observed it.",
            ]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'governance' ), 'type' => 'file'],
            ]],
            $this->articleHero( 'Make governance earn its place', 'We design project governance around the decisions and exceptions that genuinely require senior attention.' ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'The handover starts in week one',
            'title' => 'The Handover Starts in Week One',
            'path' => 'the-handover-starts-in-week-one',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'The handover starts in week one',
                "A final handover session cannot transfer months of context. By then, the reasoning behind the work has often been compressed into templates and slides, while the client team has had little chance to practise the decisions it will soon own.\n\nDurable consulting work builds ownership throughout delivery. Client colleagues take part in the analysis, challenge the design, run the routines, and improve the materials before the engagement closes.",
                $this->img( 'handover' )
            ),
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Ownership grows through the work',
                'files' => [
                    ['id' => $this->slideImg( 'model' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'team' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'handover' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Move responsibility deliberately',
                'header' => 'row',
                'table' => [
                    ['Stage', 'Adviser role', 'Client role'],
                    ['Frame', 'Bring structure and an outside view', 'Set context, constraints, and ambition'],
                    ['Design', 'Facilitate choices and test coherence', 'Choose, challenge, and adapt'],
                    ['Practise', 'Coach and observe', 'Run the routine with live work'],
                    ['Embed', 'Support exceptions', 'Own the rhythm and improve it'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The transition is complete when the internal team can explain why the model works, operate it under normal pressure, and change it without waiting for permission from its designers. Documentation supports that confidence; it cannot create it on its own.",
            ]],
            $this->articleHero( 'Build capability into delivery', 'Our engagements are structured so your team owns the method, the evidence, and the next improvement.' ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'When a project needs recovery, not more reporting',
            'title' => 'When a Project Needs Recovery, Not More Reporting',
            'path' => 'when-a-project-needs-recovery-not-more-reporting',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'When a project needs recovery, not more reporting',
                "Troubled programmes often produce more reporting just as confidence falls. New trackers, assurance meetings, and narrative updates create activity around the problem without changing the conditions that caused it.\n\nRecovery begins when leaders are willing to re-open the delivery premise: the outcome, scope, sequence, authority, and capacity available. The aim is not to defend the original plan. It is to establish a plan the organisation can now execute.",
                $this->img( 'dashboard' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'systems' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "### Look for structural signals\n\nRepeated milestone movement, unresolved cross-team dependencies, unclear acceptance criteria, and decisions that return to the agenda are not separate reporting issues. Together they indicate that the delivery system cannot convert effort into closure.\n\nA recovery review traces those patterns back to their causes and identifies the few interventions that change the path.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Reporting response or recovery response',
                'header' => 'row',
                'table' => [
                    ['Signal', 'Reporting response', 'Recovery response'],
                    ['Milestones keep moving', 'Request a revised date', 'Rebuild the sequence from real dependencies'],
                    ['Scope remains disputed', 'Add detail to the scope log', 'Name the outcome and make explicit trade-offs'],
                    ['Risks stay open', 'Escalate the risk rating', 'Assign authority and fund the mitigation'],
                    ['Teams wait on each other', 'Track more dependencies', 'Change ownership or integration cadence'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'html', 'group' => 'main', 'data' => [
                'text' => '<aside><strong>A recovery plan should make the work smaller before it makes the reporting larger.</strong> Protect the outcome, remove non-essential scope, and restore a sequence teams can finish.</aside>',
            ]],
            $this->articleHero( 'Recover the delivery path', 'We provide an independent view of troubled programmes and a short, owned route back to credible delivery.' ),
        ], $blog );

        return $this;
    }


    /**
     * Creates the two-page client handbook below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addDocs( Page $home ) : static
    {
        $docs = $this->page( [
            'lang' => 'en',
            'name' => 'Client handbook',
            'title' => 'Client Handbook | Meridian Works',
            'path' => 'docs',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Before the work begins',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Every Meridian Works engagement begins with a short mobilisation period. We confirm the outcome, decision owners, working team, existing evidence, access requirements, and dates that cannot move.\n\nThe first week is designed to reduce ambiguity, not to fill calendars. We will ask for the smallest useful group of interviews and documents, then return a clear view of what we heard, what remains uncertain, and how the work should proceed.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What we ask you to name',
                'cards' => [
                    ['title' => 'One accountable sponsor', 'text' => 'The person who owns the outcome and can resolve choices beyond the working team.', 'file' => ['id' => $this->img( 'team' ), 'type' => 'file']],
                    ['title' => 'A working counterpart', 'text' => 'The colleague who keeps context moving and will own the routines after the engagement.', 'file' => ['id' => $this->img( 'handover' ), 'type' => 'file']],
                    ['title' => 'A real operating question', 'text' => 'The decision or performance problem the work must resolve—not a list of requested outputs.', 'file' => ['id' => $this->img( 'decisions' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'The weekly rhythm',
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Typical engagement cadence',
                'header' => 'row',
                'table' => [
                    ['Moment', 'Purpose', 'People'],
                    ['Working session', 'Develop and test the current piece of work', 'Client counterpart and delivery team'],
                    ['Written update', 'Record progress, decisions, evidence, and exceptions', 'Shared with the full project group'],
                    ['Sponsor check-in', 'Resolve choices outside the team mandate', 'Sponsor and engagement lead'],
                    ['Playback', 'Test the work with people who must use it', 'Operational owners and affected teams'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'delivery' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### One shared working space\n\nWe keep current outputs, decisions, actions, and source material in the client's chosen workspace. Email may announce a change; it should not become the only place where the change exists.\n\nSensitive material stays within the access boundary agreed during mobilisation. We do not move client documents into personal storage or unapproved collaboration tools.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'A concise project brief',
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'markdown'],
                'text' => "# Engagement brief\n\nOutcome:\nDecision owner:\nWorking counterpart:\nIn scope:\nOut of scope:\nEvidence available:\nConstraints:\nFirst decision date:\nMeasures of progress:",
            ]],
            ['id' => Utils::uid(), 'type' => 'file', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->guideFile(), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Practical questions',
                'items' => [
                    ['title' => 'How much client time should we plan for?', 'text' => 'A working counterpart usually needs two to four hours each week. Sponsors need a short weekly decision window and attendance at planned playbacks. We agree the exact rhythm before mobilisation.'],
                    ['title' => 'Can the scope change?', 'text' => 'Yes, when new evidence changes the sensible route. We record the reason, effect on cost or timing, and the person approving the change before work moves.'],
                    ['title' => 'Who owns the material?', 'text' => 'Client-specific outputs and working material belong to the client under the terms of the engagement. Meridian Works retains its pre-existing methods and general know-how.'],
                ],
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Project governance',
            'title' => 'Project Governance | Meridian Works Client Handbook',
            'path' => 'docs/project-governance',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Govern the decisions, not the advisers',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Governance should make authority visible and decisions timely. It should not create a parallel management structure around the engagement. We adapt to the organisation's existing forums where they work and add a new meeting only when a genuine decision has no home.\n\nEach forum receives a defined mandate: what it may decide, what evidence it expects, and which issues must move elsewhere.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Core roles',
                'header' => 'row',
                'table' => [
                    ['Role', 'Accountability', 'Typical decisions'],
                    ['Sponsor', 'Business outcome and organisational authority', 'Priority, funding, major scope, unresolved trade-offs'],
                    ['Client counterpart', 'Day-to-day ownership and continuity', 'Working sequence, access, participation, routine choices'],
                    ['Engagement lead', 'Quality and integrity of the advisory work', 'Method, staffing, evidence standard, escalation'],
                    ['Operational owners', 'Use and sustainability of the result', 'Practical design, adoption, local implementation'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Keep four records current',
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'columns' => '4',
                'cards' => [
                    ['title' => 'Decisions', 'text' => 'The choice, owner, date, evidence, conditions, and consequence.'],
                    ['title' => 'Actions', 'text' => 'A named owner, a useful due date, and a clear completion test.'],
                    ['title' => 'Risks', 'text' => 'Cause, consequence, response, owner, and next review point.'],
                    ['title' => 'Changes', 'text' => 'The reason, impact, alternatives, approval, and revised baseline.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'governance' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "### Escalate with a recommendation\n\nAn escalation should state what happened, why it matters, what the team has already tried, and which decision is required. Wherever possible, it should include a recommendation and the consequence of waiting.\n\nThis gives the sponsor something they can resolve. A red status without a decision request transfers anxiety rather than authority.",
            ]],
            ['id' => Utils::uid(), 'type' => 'html', 'group' => 'main', 'data' => [
                'text' => '<aside><strong>Governance is working when the delivery team leaves each forum with fewer unresolved choices than it brought in.</strong></aside>',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Close with ownership',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The closeout confirms which outcomes were reached, which items remain open, who owns them, where the final material lives, and how success will be reviewed after the engagement. We also record decisions that should not be reopened without new evidence.\n\nThe final meeting is a confirmation of ownership already practised during delivery—not the moment ownership is handed over for the first time.",
            ]],
        ], $docs );

        return $this;
    }


    /**
     * Creates an article lead element with the file reference used by previews.
     *
     * @param string $title Article title
     * @param string $text Article introduction
     * @param string $fileId Cover file ID
     * @return array<string, mixed> Article content element
     */
    protected function article( string $title, string $text, string $fileId ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => [$fileId], 'data' => [
            'title' => $title,
            'file' => ['id' => $fileId, 'type' => 'file'],
            'text' => $text,
        ]];
    }


    /**
     * Creates a closing call to action for an article.
     *
     * @param string $title Hero title
     * @param string $text Hero text
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'Discuss the work',
            'text' => $text,
            'url' => '/#contact',
            'button' => 'Start a conversation',
        ]];
    }


    /**
     * Creates the shared Meridian Works footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Practice', 'text' => "- [Operating model design](/decisions-before-deliverables)\n- [Service improvement](/docs)\n- [Delivery recovery](/when-a-project-needs-recovery-not-more-reporting)"],
                ['title' => 'Resources', 'text' => "- [Client handbook](/docs)\n- [Project governance](/docs/project-governance)\n- [Field notes](/blog)"],
                ['title' => 'Contact', 'text' => "- [Email Meridian Works](mailto:hello@meridianworks.example)\n- [Discuss an engagement](/#contact)\n- [Read the client handbook](/docs)"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Meridian Works footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Meridian Works footer',
                    'data' => ['cards' => $cards],
                ],
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary Meridian Works image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'hero' );
    }


    /**
     * Creates a downloadable project brief template and returns its ID.
     *
     * @return string File ID
     */
    protected function guideFile() : string
    {
        if( !isset( $this->guideFile ) )
        {
            $data = [
                'mime' => 'application/pdf',
                'lang' => 'en',
                'name' => 'Meridian Works project brief template',
                'path' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'previews' => [],
                'description' => ['en' => 'Downloadable template for defining an engagement outcome, ownership, scope, evidence, and constraints'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->guideFile = (string) $file->refresh()->id;
        }

        return $this->guideFile;
    }


    /**
     * Creates the Meridian Works home page and returns it.
     *
     * @param string $blogId Field-notes page ID referenced by listing elements
     * @return Page Home page
     */
    protected function home( string $blogId ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $logoId = $this->logoFile();

        $config = [
            'logo' => [
                'type' => 'logo',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
            'logo-alternative' => [
                'type' => 'logo-alternative',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Make complex change workable',
                'subtitle' => 'Meridian Works',
                'text' => 'We help leadership teams redesign services, clarify operating models, and recover important work when delivery has lost its way.',
                'url' => '#contact',
                'button' => 'Discuss an engagement',
                'url-alternative' => '/docs',
                'button-alternative' => 'Read the client handbook',
                'files' => [['id' => $fileId, 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Where we help',
                'cards' => [
                    ['title' => 'Operating model design', 'text' => 'Clarify accountability, decision rights, interfaces, and management routines around the work that matters.', 'file' => ['id' => $this->img( 'model' ), 'type' => 'file']],
                    ['title' => 'Service improvement', 'text' => 'Find where a service loses time, trust, or value, then redesign the flow with the people who run it.', 'file' => ['id' => $this->img( 'systems' ), 'type' => 'file']],
                    ['title' => 'Delivery recovery', 'text' => 'Give sponsors an independent view of a troubled programme and a credible sequence back to control.', 'file' => ['id' => $this->img( 'dashboard' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'meeting' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Work close to the decision\n\nWe combine analysis with direct work alongside sponsors, operational leaders, and delivery teams. The aim is to reach a sound decision, put it into practice, and leave behind a routine the organisation can run without us.\n\nYou will see the evidence, trade-offs, and open questions as the work develops. No theatre, no late reveal.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'What an engagement produces',
                'header' => 'row',
                'table' => [
                    ['Need', 'Our contribution', 'Result'],
                    ['A confused mandate', 'Frame the outcome, constraints, and decision path', 'A brief leaders and teams can use'],
                    ['A fragmented service', 'Trace demand, work, hand-offs, and failure points', 'A practical service design and change sequence'],
                    ['Unclear accountability', 'Define ownership, interfaces, and management rhythm', 'An operating model grounded in real work'],
                    ['A slipping programme', 'Test the plan, dependencies, capacity, and governance', 'A recovery plan with named decisions and owners'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What clients value',
                'items' => [
                    ['name' => 'Eleanor Price', 'role' => 'Chief Operating Officer, Northbank Housing', 'text' => 'Meridian Works gave us a language for decisions we had been circling for months. The new service model was clear enough for teams to challenge, then run.'],
                    ['name' => 'Matteo Klein', 'role' => 'Transformation Director, Aster Mobility', 'text' => 'They were direct about what the programme could not deliver, but equally clear about the route back. Our steering group finally had decisions it could make.'],
                    ['name' => 'Ruth Okafor', 'role' => 'Director of Customer Operations, Calder & Finch', 'text' => 'The work never felt handed down to us. My managers built the new routines with the advisers and were already running them before the engagement closed.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Ways to engage',
                'text' => 'Each engagement is scoped around a decision and a usable outcome. These formats are starting points, not packaged answers.',
                'items' => [
                    ['name' => 'Diagnostic', 'price' => '2–3 weeks', 'unit' => '', 'text' => 'An independent assessment before a major commitment, recovery, or strategic reset.', 'features' => "- Evidence and stakeholder review\n- Decision and dependency map\n- Findings playback\n- Prioritised next steps", 'url' => '#contact', 'button' => 'Discuss a diagnostic'],
                    ['name' => 'Design+Launch', 'price' => '6–10 weeks', 'unit' => '', 'text' => 'A new operating model, service, or delivery approach built with your team.', 'features' => "- Current-state analysis\n- Design with operational owners\n- Tested routines and measures\n- Mobilisation plan", 'url' => '#contact', 'button' => 'Plan the work', 'highlight' => true, 'badge' => 'Typical engagement'],
                    ['name' => 'Delivery counsel', 'price' => 'Retained', 'unit' => '', 'text' => 'Experienced challenge and support around a live change portfolio.', 'features' => "- Sponsor counsel\n- Independent delivery reviews\n- Decision preparation\n- Facilitation at critical points", 'url' => '#contact', 'button' => 'Talk about support'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the field notes',
                'layout' => 'cards',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $blogId, 'label' => 'Field notes'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before we begin',
                'items' => [
                    ['title' => 'What size organisations do you work with?', 'text' => 'Most clients are established organisations with work crossing several teams, functions, or regions. The defining factor is the complexity of the decision, not headcount.'],
                    ['title' => 'Do you implement the recommendations?', 'text' => 'Yes. We design engagements around mobilisation and early operation, not a final report. The balance of advisory and hands-on support depends on your team and the outcome.'],
                    ['title' => 'Can you review a programme confidentially?', 'text' => 'Yes. We agree the sponsor, access boundary, interview approach, and handling of sensitive evidence before the review begins.'],
                    ['title' => 'How quickly can an engagement start?', 'text' => 'A focused diagnostic can usually begin within a few weeks once sponsorship, access, and the core question are clear.'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Bring us the decision that is not moving',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'Meridian Works']],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Meridian Works helps leadership teams redesign services, clarify operating models, and recover complex transformation programmes.',
                'keywords' => 'operating model consulting, service design, delivery recovery, transformation advisory, professional services',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Meridian Works | Make Complex Change Workable',
                'description' => 'Practical advisory work for operating models, service improvement, and delivery recovery.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Meridian Works | Make Complex Change Workable',
            'path' => '',
            'tag' => 'root',
            'theme' => $this->theme,
            'status' => 1,
            'cache' => 5,
            'editor' => 'demo',
            'config' => $config,
            'meta' => $meta,
            'content' => $content,
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => 'Meridian Works | Make Complex Change Workable',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => [
                'config' => $config,
                'meta' => $meta,
                'content' => $content,
            ],
            'editor' => 'demo',
        ] );

        $version->files()->attach( array_unique( array_merge( [$fileId], $this->ids( $config ), $this->ids( $content ), $this->ids( $meta ) ) ) );
        $version->elements()->attach( $elementId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Returns file IDs referenced anywhere in the given data.
     *
     * @param mixed $value Content or metadata
     * @return array<int, string> File IDs
     */
    protected function ids( mixed $value ) : array
    {
        $ids = [];

        if( is_array( $value ) )
        {
            if( ( $value['type'] ?? null ) === 'file' && is_string( $value['id'] ?? null )
                && !isset( $value['data'] ) && !isset( $value['group'] )
            ) {
                $ids[] = $value['id'];
            }

            foreach( $value as $item ) {
                $ids = array_merge( $ids, $this->ids( $item ) );
            }
        }

        return $ids;
    }


    /**
     * Returns the file ID for a curated demo photo.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function img( string $key ) : string
    {
        [$photo, $name, $desc] = self::PHOTOS[$key];
        return $this->image( $photo, $name, $desc );
    }


    /**
     * Creates the Meridian Works SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 380 80" role="img" aria-labelledby="title desc">
  <title id="title">Meridian Works logo</title>
  <desc id="desc">Meridian Works wordmark with an abstract compass and letter M</desc>
  <g fill="none" fill-rule="evenodd">
    <circle cx="40" cy="40" r="29" stroke="#111827" stroke-width="2"/>
    <path d="m40 6 7 18-7-4-7 4Z" fill="#DC2626"/>
    <path d="m40 74-7-17 7 4 7-4Z" fill="#DC2626" fill-opacity=".75"/>
    <path d="M19 54V27l21 20 21-20v27" stroke="#111827" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="m34 40 6 7 6-7" stroke="#DC2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    <text x="86" y="39" fill="#111827" font-family="ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" font-size="28" font-weight="700" letter-spacing="-.6">MERIDIAN</text>
    <path d="M88 50h24" stroke="#DC2626" stroke-width="3" stroke-linecap="round"/>
    <text x="122" y="58" fill="#505460" font-family="ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" font-size="13" font-weight="650" letter-spacing="5">WORKS</text>
  </g>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/meridian-works-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'Meridian Works logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'Meridian Works wordmark with an abstract compass and letter M'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->logoFile = (string) $file->refresh()->id;
        }

        return $this->logoFile;
    }


    /**
     * Creates a default demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @param array<int, string> $fileIds Additional file IDs to attach
     * @param array<string, array<string, mixed>|object> $meta Meta entries keyed by type
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'Meridian Works, management consulting, operating model, service improvement, delivery recovery',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $content[] = ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'Meridian Works']];
        $content[] = ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'];

        $page = Page::forceCreate( $data + [
            'theme' => $this->theme,
            'editor' => 'demo',
            'meta' => $meta,
            'content' => $content,
        ] );
        $page->appendToNode( $parent )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $data['lang'] ?? 'en',
            'data' => array_diff_key( $data, ['content' => 1, 'meta' => 1, 'id' => 1] ) + [
                'domain' => '',
                'theme' => $this->theme,
            ],
            'aux' => ['meta' => $meta, 'content' => $content],
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the default demo page tree.
     */
    protected function pages() : void
    {
        $blogId = (string) Str::uuid7();
        $home = $this->home( $blogId );

        $this->addDocs( $home )
            ->addBlog( $home, $blogId );
    }


    /**
     * Creates a fixed 2:1 slideshow image and returns its file ID.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function slideImg( string $key ) : string
    {
        if( !isset( $this->slideImages[$key] ) )
        {
            [$photo, $name, $desc] = self::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1500, 750 ),
                'previews' => ['500' => $url( 500, 250 ), '1000' => $url( 1000, 500 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->slideImages[$key] = (string) $file->refresh()->id;
        }

        return $this->slideImages[$key];
    }
}
