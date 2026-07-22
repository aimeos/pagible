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
 * Bold theme demo for the fictional RALLY Training Club.
 */
class BoldDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'training' => 'Explore RALLY strength, conditioning, hybrid, and mobility sessions: coached small-group training built for measurable progress.',
        'coaches' => 'Meet the RALLY coaching team and the specialists who make every strength, conditioning, and mobility session purposeful and personal.',
        'membership' => 'Compare RALLY Training Club drop-in, eight-session, and unlimited memberships, with transparent pricing and no joining fee.',
        'journal' => 'Read practical RALLY field notes on strength, conditioning, recovery, training consistency, and making every session count.',
        'strength-is-a-skill' => 'Learn why good strength training starts with repeatable movement, useful feedback, and patient progress rather than constant exhaustion.',
        'the-case-for-easy-conditioning' => 'A clear guide to conversational-pace conditioning and why easier aerobic work belongs beside hard intervals.',
        'how-to-build-a-week-that-holds' => 'Build a realistic training week around strength, conditioning, recovery, work, sleep, and the life you actually lead.',
        'what-progress-looks-like-after-six-weeks' => 'See the useful signals RALLY coaches track during a six-week training block, from technique and pace to confidence and consistency.',
        'studio-guide' => 'Plan your RALLY visit with practical guidance on booking, arrival, equipment, class levels, recovery, and studio etiquette.',
        'studio-guide/first-session' => 'Know what to expect at your first RALLY session, including arrival, coach check-in, class structure, equipment, and scaling.',
        'studio-guide/booking-and-recovery' => 'Manage RALLY bookings, waitlists, cancellations, rest days, and training frequency without turning fitness into a second job.',
        'start' => 'Book a first RALLY Training Club session, arrange a studio tour, or ask the coaching team which class and membership suit you.',
    ];

    /**
     * Curated Unsplash photos used across the RALLY demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'athlete' => ['photo-1517836357463-d25dfeac3438', 'RALLY strength athlete', 'Athlete training with free weights in a focused strength session'],
        'battle-rope' => ['photo-1538805060514-97d9cc17730c', 'RALLY conditioning session', 'Athlete driving through an energetic conditioning interval'],
        'coach' => ['photo-1571019613454-1cb2f99b2d8b', 'RALLY coach', 'Coach giving clear movement feedback during a small-group training session'],
        'community' => ['photo-1518611012118-696072aa579a', 'RALLY training community', 'Training partners moving together during an energetic studio class'],
        'detail' => ['photo-1581009146145-b5ef050c2e1e', 'Strength training detail', 'Close view of an athlete controlling a demanding strength movement'],
        'floor' => ['photo-1534438327276-14e5300c3a48', 'RALLY training floor', 'Spacious strength and conditioning studio with functional training equipment'],
        'interval' => ['photo-1599058917212-d750089bc07e', 'Interval training', 'Athlete working at pace during a coached interval session'],
        'mobility' => ['photo-1552196563-55cd4e45efb3', 'RALLY mobility session', 'Athlete working through a controlled mobility sequence on a training mat'],
        'plates' => ['photo-1517963879433-6ad2b056d712', 'Training equipment', 'Weight plates and strength equipment prepared on the studio floor'],
        'recovery' => ['photo-1544367567-0f2fcb009e0b', 'Active recovery', 'Athlete using a quiet floor sequence to recover between training days'],
        'run' => ['photo-1552674605-db6ffd4facb5', 'RALLY run crew', 'Runners moving together through an outdoor conditioning session'],
        'sprint' => ['photo-1530137073520-4ea6e2f10a48', 'Sprint training', 'Athlete accelerating through a fast track effort'],
        'team' => ['photo-1526506118085-60ce8714f8c5', 'RALLY coaching team', 'Coaches and athletes gathered on the training floor after a session'],
        'warmup' => ['photo-1605296867304-46d5465a13f1', 'RALLY warm-up', 'Athlete preparing for a coached strength and conditioning workout'],
        'woman' => ['photo-1549476464-37392f717541', 'RALLY athlete', 'Athlete training confidently with free weights in a bright gym'],
    ];

    private string $element;
    private string $logoFile;
    /** @var array<string, string> File IDs for fixed-ratio slideshow images */
    private array $slideImages = [];


    /**
     * Creates the training journal and its articles below the home page.
     *
     * @param Page $home Home page
     * @param string $journalId Journal page ID referenced by listing elements
     * @return static Same object for fluent calls
     */
    protected function addBlog( Page $home, string $journalId ) : static
    {
        $journal = $this->page( [
            'id' => $journalId,
            'lang' => 'en',
            'name' => 'Journal',
            'title' => 'RALLY Field Notes',
            'path' => 'journal',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Train smarter. Keep showing up.',
                'subtitle' => 'RALLY Field Notes',
                'text' => 'Useful thinking from our coaches on strength, conditioning, recovery, and building a practice that survives busy weeks.',
                'files' => [['id' => $this->img( 'detail' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Latest field notes',
                'layout' => 'default',
                'limit' => 4,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Strength is a skill',
            'title' => 'Strength Is a Skill Before It Is a Number',
            'path' => 'strength-is-a-skill',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Strength is a skill before it is a number',
                "A heavier bar can be satisfying, but the useful work starts earlier: finding a stable position, creating tension on purpose, and repeating the same movement when breathing gets loud.\n\nThat is why RALLY coaches treat strength as a skill. Load matters. So does the quality you can reproduce next week.",
                $this->img( 'athlete' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Build the movement you want to load',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'coach' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "Your first working set gives the coach information. Can you keep pressure through the floor? Does the weight stay close? Is the final repetition recognisably the same movement as the first?\n\nA useful cue should make the next repetition clearer. If it gives you six things to think about, it is not useful yet. We change one variable, let you feel the difference, then build from there.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'A simple progression ladder',
                'header' => 'row',
                'table' => [
                    ['Step', 'What changes', 'What stays steady'],
                    ['Own the pattern', 'Range and tempo', 'A load you can control'],
                    ['Add volume', 'One or two quality repetitions', 'Position and breathing'],
                    ['Add load', 'The smallest useful increase', 'Repetition quality'],
                    ['Add intent', 'More speed or density', 'The movement standard'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Progress is not a test you pass once. Some days the right move is more weight; on others it is a cleaner pause, a steadier tempo, or stopping one repetition earlier.\n\nThe goal is not to make every session dramatic. It is to leave with work you can absorb and a clear next step.",
            ]],
            $this->articleHero(
                'Put the next rep in good hands',
                'Our coached strength sessions give you a clear plan, useful feedback, and room to progress at your pace.'
            ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'The case for easy conditioning',
            'title' => 'The Case for Easy Conditioning',
            'path' => 'the-case-for-easy-conditioning',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'The case for easy conditioning',
                "Hard intervals are memorable. The quieter work between them is what often makes them repeatable.\n\nConversational-pace conditioning builds the ability to move for longer, recover between efforts, and finish a session with something left. It should feel controlled enough that you could do more, even when the clock says you are done.",
                $this->img( 'run' )
            ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three signs the pace is right',
                'cards' => [
                    ['title' => 'You can speak', 'text' => 'Short sentences are comfortable and your breathing remains rhythmic rather than urgent.'],
                    ['title' => 'You stay smooth', 'text' => 'Your stride, stroke, or pedal rhythm looks much the same near the end as it did at the start.'],
                    ['title' => 'You recover quickly', 'text' => 'Breathing settles soon after the work and the session does not take over the rest of your day.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'interval' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Easy does not mean careless\n\nThe target is control, not drifting through the hour. Choose a pace you can hold, keep transitions tidy, and resist the urge to win the warm-up.\n\nOn bikes and rowers, use a consistent output. When running, keep the stride relaxed enough that posture and foot placement stay quiet. The work accumulates because you do not keep interrupting it.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Where each effort belongs',
                'header' => 'row+col',
                'table' => [
                    ['Session', 'Effort', 'Typical length', 'Main purpose'],
                    ['Base', 'Conversational', '30–50 minutes', 'Aerobic capacity and recovery'],
                    ['Tempo', 'Controlled but focused', '15–30 minutes', 'Sustained pace and efficiency'],
                    ['Intervals', 'Hard with full intent', '8–20 minutes of work', 'Speed, power, and high-end capacity'],
                ],
            ]],
            $this->articleHero(
                'Build an engine that comes back tomorrow',
                'RALLY Engine sessions combine purposeful intervals with the aerobic work that helps them land.'
            ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'How to build a week that holds',
            'title' => 'How to Build a Training Week That Holds',
            'path' => 'how-to-build-a-week-that-holds',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'How to build a training week that holds',
                "The perfect plan is useless if it collapses every Thursday. A good week has enough structure to create progress and enough room to survive deadlines, poor sleep, late trains, and the occasional dinner that matters more.\n\nStart with the sessions you can protect. Build from those anchors instead of drafting an imaginary life around six ideal training days.",
                $this->img( 'warmup' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'recovery' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Leave space between the hard things\n\nTwo or three focused sessions can move you forward. Put the most demanding work where you are usually rested, then use easier conditioning, walking, and mobility to connect those days.\n\nIf work becomes intense, keep the appointment and reduce the dose. Thirty useful minutes protects the habit better than cancelling because sixty no longer fits.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Three workable weeks',
                'header' => 'row',
                'table' => [
                    ['Frequency', 'Example', 'Best fit'],
                    ['Two sessions', 'Strength + Hybrid', 'A strong minimum beside another sport or a full schedule'],
                    ['Three sessions', 'Strength + Engine + Strength', 'Balanced progress with clear recovery days'],
                    ['Four sessions', 'Strength + Engine + Strength + Mobility', 'More practice without making every day hard'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Adjusting the week',
                'items' => [
                    ['title' => 'What if I miss a session?', 'text' => 'Continue with the next useful session. Do not compress a whole week of hard work into the days that remain.'],
                    ['title' => 'Should soreness decide?', 'text' => 'Use soreness as one signal alongside sleep, energy, movement quality, and how the warm-up feels. Ask the coach to adjust the day when needed.'],
                    ['title' => 'How often should the plan change?', 'text' => 'Keep the main structure long enough to learn from it. Small weekly adjustments are usually more useful than a complete reset.'],
                ],
            ]],
            $this->articleHero(
                'Build your week with a coach',
                'Tell us what your schedule can hold and we will help you choose a starting rhythm that feels ambitious and realistic.'
            ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'What progress looks like after six weeks',
            'title' => 'What Progress Looks Like After Six Weeks',
            'path' => 'what-progress-looks-like-after-six-weeks',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'What progress looks like after six weeks',
                "Six weeks is long enough to notice change and short enough to remember where you started. The clearest progress is not always a dramatic personal record. It may be the same load with better control, a faster recovery between rounds, or arriving without the uncertainty that marked week one.",
                $this->img( 'woman' )
            ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Four signals worth keeping',
                'cards' => [
                    ['title' => 'Technique', 'text' => 'Positions feel familiar and useful coaching cues take effect more quickly.'],
                    ['title' => 'Capacity', 'text' => 'A repeatable pace rises or the same work asks for less recovery.'],
                    ['title' => 'Confidence', 'text' => 'You can choose an appropriate load and adjust a movement without losing the session.'],
                    ['title' => 'Consistency', 'text' => 'Training has a place in the week that no longer depends on a burst of motivation.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'plates' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Test what you have trained\n\nAt the end of a block, we repeat a small number of relevant markers: a controlled strength set, a steady conditioning piece, or a movement standard that was difficult in week one.\n\nThe test should clarify the next block, not reduce six weeks of work to a pass or fail. A useful result tells us what is ready to advance and what deserves more practice.",
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'The changes members noticed',
                'items' => [
                    ['name' => 'Noor A.', 'role' => 'RALLY member', 'text' => 'I stopped guessing which weight to use. By week six I could make a good decision before the coach reached me.'],
                    ['name' => 'Leon B.', 'role' => 'RALLY member', 'text' => 'The conditioning still challenged me, but I was ready for the next round instead of simply surviving it.'],
                    ['name' => 'Marta K.', 'role' => 'RALLY member', 'text' => 'The biggest win was ordinary: three sessions became part of my week without a negotiation every time.'],
                ],
            ]],
            $this->articleHero(
                'Start a six-week training block',
                'Begin with a coached baseline and leave your first session knowing exactly what comes next.'
            ),
        ], $journal );

        return $this;
    }


    /**
     * Creates the coaching team page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCoaches( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Coaches',
            'title' => 'RALLY Coaches',
            'path' => 'coaches',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Coaching you can use on the next rep.',
                'subtitle' => 'Meet the team',
                'text' => 'Clear eyes, calm cues, serious standards. Our coaches know when to push, when to simplify, and how to keep a full room moving without losing the individual.',
                'url' => '/start',
                'button' => 'Meet us on the floor',
                'url-alternative' => '/training',
                'button-alternative' => 'Explore the sessions',
                'files' => [['id' => $this->img( 'team' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Your coaching team',
                'columns' => 4,
                'cards' => [
                    ['title' => 'Maya Okafor', 'text' => "**Head coach · Strength**\n\nMaya turns complex lifts into cues you can feel. Her sessions are direct, generous, and built around repeatable quality.", 'file' => ['id' => $this->img( 'coach' ), 'type' => 'file']],
                    ['title' => 'Jonas Weber', 'text' => "**Conditioning · Run coach**\n\nJonas programs pace with purpose, from first aerobic blocks to race-specific intervals that do not waste a metre.", 'file' => ['id' => $this->img( 'run' ), 'type' => 'file']],
                    ['title' => 'Ari Kim', 'text' => "**Hybrid · Athletic development**\n\nAri blends strength, speed, and coordination into sessions that feel athletic without becoming chaotic.", 'file' => ['id' => $this->img( 'sprint' ), 'type' => 'file']],
                    ['title' => 'Elena Rossi', 'text' => "**Mobility · Return to training**\n\nElena helps members restore useful range, understand their options, and return to full sessions with confidence.", 'file' => ['id' => $this->img( 'mobility' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'detail' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## One room. More than one right option.\n\nEvery RALLY session has a clear training intent. The exact movement can change. Coaches offer progressions that preserve the purpose of the day, whether you are learning the pattern, building back after time away, or ready to increase the demand.\n\nScaling is not a side route. It is how good group training stays personal.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Who to speak to',
                'header' => 'row',
                'table' => [
                    ['Goal or question', 'Coach', 'Best starting point'],
                    ['Build confident strength technique', 'Maya', 'RALLY Strength'],
                    ['Improve aerobic pace or prepare for a run', 'Jonas', 'RALLY Engine'],
                    ['Combine lifting, speed, and conditioning', 'Ari', 'RALLY Hybrid'],
                    ['Return after time away or improve range', 'Elena', 'RALLY Mobility'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What good coaching feels like',
                'items' => [
                    ['name' => 'Dina S.', 'role' => 'Member since 2024', 'text' => 'Maya gave me one cue and the lift clicked. No lecture, no performance—just the right piece of information at the right time.'],
                    ['name' => 'Felix R.', 'role' => 'RALLY Engine member', 'text' => 'Jonas can challenge a fast runner and a complete beginner in the same interval without making either feel misplaced.'],
                    ['name' => 'Priya N.', 'role' => 'Member since 2023', 'text' => 'The coaches remember what I am working on. Group training here never feels anonymous.'],
                ],
            ]],
            ['id' => 'coach-contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Tell the coaches what you are training for',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the practical studio guide below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addDocs( Page $home ) : static
    {
        $guide = $this->page( [
            'lang' => 'en',
            'name' => 'Studio guide',
            'title' => 'RALLY Studio Guide',
            'path' => 'studio-guide',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Everything you need. Nothing to decode.',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "RALLY is a coached training studio in Kreuzberg, Berlin. Sessions run for 50 minutes and groups stay small enough for coaches to know who is in the room.\n\nYou do not need to arrive fit, know the movements, or buy specialist equipment. Bring clothes you can move in, clean trainers, and a water bottle. We provide the training plan, equipment, towels, lockers, and the context behind the work.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Start here',
                'cards' => [
                    ['title' => 'Your first session', 'text' => "Arrival, coach check-in, class structure, and choosing the right movement option.\n\n[Prepare for your first session](/studio-guide/first-session)", 'file' => ['id' => $this->img( 'warmup' ), 'type' => 'file']],
                    ['title' => 'Booking and recovery', 'text' => "Reservations, waitlists, cancellations, training frequency, and making room for recovery.\n\n[Plan your training week](/studio-guide/booking-and-recovery)", 'file' => ['id' => $this->img( 'recovery' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Choose your first class',
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Session guide',
                'header' => 'row+col',
                'table' => [
                    ['Session', 'Focus', 'Experience needed', 'Good first choice'],
                    ['RALLY Strength', 'Foundational lifts and controlled accessory work', 'None', 'Yes'],
                    ['RALLY Engine', 'Aerobic work, intervals, and pacing', 'None', 'Yes'],
                    ['RALLY Hybrid', 'Strength and conditioning in one session', 'Some training helps', 'Ask a coach'],
                    ['RALLY Mobility', 'Range, control, and recovery', 'None', 'Yes'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'The room belongs to everyone training in it',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Move with awareness, return equipment after use, and leave coaching space around every athlete. Phones stay away from the training floor. Photos are only taken with the clear agreement of everyone visible.\n\nTell the coach about pain, an injury, pregnancy, or anything else that could affect the session. You decide what happens with your body; the coach's job is to offer informed options.",
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Your first session',
            'title' => 'Your First RALLY Session',
            'path' => 'studio-guide/first-session',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Arrive ten minutes early',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Check in at the front desk and tell us it is your first visit. A coach will show you the changing area and training floor, ask about your recent training and any movement considerations, then explain the focus of the class.\n\nIf you are running late, call before the session starts. For safety, the floor closes to arrivals once the coached warm-up is underway.",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'floor' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### The 50-minute shape\n\n**Briefing — 5 minutes:** the goal, movements, and available options.  \n**Warm-up — 10 minutes:** raise temperature and practise the positions you will use.  \n**Main work — 28 minutes:** coached strength, conditioning, or a deliberate combination.  \n**Reset — 7 minutes:** bring breathing down, note your result, and ask what should come next.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What to bring',
                'cards' => [
                    ['title' => 'Training clothes', 'text' => 'Wear something you can squat, reach, and breathe freely in. There is no studio uniform.'],
                    ['title' => 'Clean trainers', 'text' => 'Stable cross-training shoes suit most sessions. Running shoes are fine for Engine days.'],
                    ['title' => 'Water bottle', 'text' => 'Filtered water is available. Towels, lockers, shower products, and equipment are provided.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'First-session questions',
                'items' => [
                    ['title' => 'Do I need to be fit before I start?', 'text' => 'No. Choose a session you can repeat, and let fitness be the result of training rather than an entry requirement.'],
                    ['title' => 'What if I cannot do a movement?', 'text' => 'The coach will offer another movement that keeps the purpose of the session. You never need to force a painful or unsuitable option.'],
                    ['title' => 'Will everyone else be advanced?', 'text' => 'Members train at different levels in the same room. Loads, pace, range, and complexity are adjusted individually.'],
                ],
            ]],
        ], $guide );

        $this->page( [
            'lang' => 'en',
            'name' => 'Booking and recovery',
            'title' => 'Booking and Recovery at RALLY',
            'path' => 'studio-guide/booking-and-recovery',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Book the week you can keep',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Sessions open for booking fourteen days ahead. Reserve in the member app or at reception. If a class is full, join the waitlist: places are offered in order and you will receive a notification when one becomes available.\n\nCancel at least eight hours before class to return the session credit. Late cancellations and no-shows use the credit because the place could no longer be offered reliably to another member.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Booking windows',
                'header' => 'row',
                'table' => [
                    ['Action', 'Window', 'What happens'],
                    ['Book', 'Up to 14 days ahead', 'Your place is confirmed immediately'],
                    ['Join waitlist', 'Until class starts', 'You are notified automatically if a place opens'],
                    ['Cancel', '8+ hours before class', 'The session credit returns to your account'],
                    ['Late cancel', 'Less than 8 hours', 'The session credit is used'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'recovery' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Recovery is part of the programme\n\nStart with two or three sessions a week and leave breathing room after demanding days. Sleep, regular meals, low-intensity movement, and time away from structured training do most of the recovery work.\n\nIf the warm-up feels unusually heavy, tell the coach. The session can often be adjusted without becoming a wasted visit.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Managing your week',
                'items' => [
                    ['title' => 'Can I train on consecutive days?', 'text' => 'Yes, when the sessions have different demands and you are recovering well. A coach can help you pair the week sensibly.'],
                    ['title' => 'How many sessions should I book?', 'text' => 'Two consistent sessions beat four that disappear after a fortnight. Begin with what fits and add only when you are recovering comfortably.'],
                    ['title' => 'Can a membership be paused?', 'text' => 'Monthly memberships can be paused for travel, illness, or major schedule changes. Contact the team before the next billing date.'],
                ],
            ]],
        ], $guide );

        return $this;
    }


    /**
     * Creates the membership page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addMembership( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Membership',
            'title' => 'RALLY Membership',
            'path' => 'membership',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Choose a rhythm. Build momentum.',
                'subtitle' => 'RALLY membership',
                'text' => 'Straightforward plans for one good session, a reliable twice-weekly practice, or training that moves with your week. No joining fee. No twelve-month lock-in.',
                'url' => '#plans',
                'button' => 'Compare plans',
                'url-alternative' => '/start',
                'button-alternative' => 'Try your first class',
                'files' => [['id' => $this->img( 'community' ), 'type' => 'file']],
            ]],
            ['id' => 'plans', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Memberships that match real weeks',
                'text' => 'Every plan includes coached sessions, full studio access during your booking, towels, lockers, and a quarterly training check-in.',
                'items' => [
                    ['name' => 'Drop-in', 'price' => '€24', 'unit' => '/class', 'text' => 'Train when you are in town or add one more session.', 'features' => "- Any regular group session\n- 14-day booking window\n- Credit valid for 30 days\n- Studio amenities included", 'url' => '/start', 'button' => 'Book one class'],
                    ['name' => 'RALLY 8', 'price' => '€129', 'unit' => '/month', 'text' => 'A steady twice-weekly rhythm with room to flex.', 'features' => "- Eight sessions each month\n- One unused credit rolls over\n- Quarterly coach check-in\n- Guest pass every three months", 'url' => '/start', 'button' => 'Start with eight', 'highlight' => true, 'badge' => 'Most popular'],
                    ['name' => 'All Access', 'price' => '€179', 'unit' => '/month', 'text' => 'For varied training weeks and members who recover well.', 'features' => "- Unlimited regular sessions\n- Priority waitlist access\n- Monthly coach check-in\n- Two guest passes each month", 'url' => '/start', 'button' => 'Go all access'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'coach' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Start with a session, not a sales call\n\nYour first class is €15. Arrive ten minutes early, meet the coach, and train with the group. Afterwards we will tell you which sessions fit your goals and answer membership questions.\n\nThere is no pressure to join before you have felt how the room works.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Compare the details',
                'header' => 'row+col',
                'table' => [
                    ['Plan', 'Sessions', 'Rollover', 'Coach check-in', 'Notice'],
                    ['Drop-in', 'One', 'Not applicable', 'After class', 'None'],
                    ['RALLY 8', 'Eight per month', 'One credit', 'Quarterly', 'One month'],
                    ['All Access', 'Unlimited', 'Not applicable', 'Monthly', 'One month'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Why members stay',
                'items' => [
                    ['name' => 'Lina V.', 'role' => 'RALLY 8 member', 'text' => 'Eight sessions gives me a target without making the calendar brittle. I train twice most weeks and move one session when work gets loud.'],
                    ['name' => 'Samir T.', 'role' => 'All Access member', 'text' => 'I can choose Strength when I feel fresh and Mobility when I need a lower gear. The membership supports both.'],
                    ['name' => 'Eva M.', 'role' => 'RALLY member', 'text' => 'The pricing was clear before I walked in. The reason I stayed was the coaching.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Membership questions',
                'items' => [
                    ['title' => 'Is there a joining fee?', 'text' => 'No. You pay only for the plan or session you choose.'],
                    ['title' => 'Can I pause a monthly membership?', 'text' => 'Yes. Plans can be paused for a full billing month for travel, illness, or a significant schedule change.'],
                    ['title' => 'Are workshops included?', 'text' => 'Regular group sessions are included. Specialist workshops and external-coach events are priced separately, with member priority.'],
                    ['title' => 'Can I change plans?', 'text' => 'Yes. Ask before the next billing date and the new plan will begin with the following month.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the first-session booking page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addStart( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Start',
            'title' => 'Start Training at RALLY',
            'path' => 'start',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your first session starts here.',
                'subtitle' => 'First class · €15',
                'text' => 'Tell us what you want from training and when you like to move. We will recommend a session, answer practical questions, and reserve your place.',
                'files' => [['id' => $this->img( 'athlete' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Pick your way in',
                'cards' => [
                    ['title' => 'Book a first class', 'text' => "Join a regular RALLY session for €15 and experience the coaching, room, and class format.\n\n[hello@rally.example](mailto:hello@rally.example)"],
                    ['title' => 'Take a studio tour', 'text' => "See the training floor, meet a coach, and talk through the timetable before booking.\n\n[Arrange a tour](mailto:hello@rally.example?subject=Studio%20tour)"],
                    ['title' => 'Ask a coach', 'text' => "Share a goal, previous injury, or concern and we will point you towards the most useful start.\n\n[coaches@rally.example](mailto:coaches@rally.example)"],
                ],
            ]],
            ['id' => 'start-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Tell us what a good start looks like',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'floor' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Find the studio\n\n**RALLY Training Club**  \nKöpenicker Straße 84  \n10997 Berlin\n\nU1/U3 to Schlesisches Tor · Bus 165/265 to Bethaniendamm  \nBike parking at the courtyard entrance\n\n**Weekdays:** 06:30–21:00  \n**Weekends:** 08:00–15:00\n\nThis is fictional demo content; the address and contact details are illustrative.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you book',
                'items' => [
                    ['title' => 'Which session is best for a beginner?', 'text' => 'RALLY Strength, Engine, and Mobility all welcome first-time members. Tell us what interests you and we will choose a suitable time.'],
                    ['title' => 'Can I visit with a friend?', 'text' => 'Yes. Mention both names in the message so we can reserve two places in the same session.'],
                    ['title' => 'What happens after I write?', 'text' => 'The front desk replies within one working day with a recommended session and a secure booking link.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the class and timetable page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTraining( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Training',
            'title' => 'Training at RALLY',
            'path' => 'training',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Four sessions. One complete training week.',
                'subtitle' => 'The RALLY method',
                'text' => 'Strength to create capacity. Conditioning to use it. Hybrid sessions to connect the pieces. Mobility to keep the options open.',
                'url' => '#sessions',
                'button' => 'Find your session',
                'url-alternative' => '/start',
                'button-alternative' => 'Book a first class',
                'files' => [
                    ['id' => $this->img( 'athlete' ), 'type' => 'file'],
                    ['id' => $this->img( 'interval' ), 'type' => 'file'],
                    ['id' => $this->img( 'mobility' ), 'type' => 'file'],
                ],
            ]],
            ['id' => 'sessions', 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Choose the work',
                'columns' => 4,
                'cards' => [
                    ['title' => 'RALLY Strength', 'text' => "Squat, hinge, press, pull, carry. Build durable strength through coached progressions and controlled volume.\n\n**50 min · All levels**", 'file' => ['id' => $this->img( 'detail' ), 'type' => 'file']],
                    ['title' => 'RALLY Engine', 'text' => "Run, row, ride, and move with a pace you understand. Aerobic work and intervals without random suffering.\n\n**50 min · All levels**", 'file' => ['id' => $this->img( 'run' ), 'type' => 'file']],
                    ['title' => 'RALLY Hybrid', 'text' => "Strength under a clock, conditioning with standards. Learn to move well when the room gets loud.\n\n**50 min · Some experience helps**", 'file' => ['id' => $this->img( 'battle-rope' ), 'type' => 'file']],
                    ['title' => 'RALLY Mobility', 'text' => "Build usable range and control through deliberate floor work, carries, and low-intensity movement.\n\n**50 min · All levels**", 'file' => ['id' => $this->img( 'mobility' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'coach' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## The plan is shared. The dose is yours.\n\nEveryone works towards the same session intent, but load, pace, range, and complexity can change. Coaches demonstrate the movement, explain why it is in the day, and give you a starting option before the clock begins.\n\nGroups are capped at fourteen. That keeps the energy of a full room and enough coaching attention to make the session personal.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'A week on the floor',
                'header' => 'row+col',
                'table' => [
                    ['Day', '06:45', '12:15', '17:30', '18:45'],
                    ['Monday', 'Strength', 'Engine', 'Strength', 'Hybrid'],
                    ['Tuesday', 'Engine', 'Mobility', 'Hybrid', 'Strength'],
                    ['Wednesday', 'Strength', 'Engine', 'Strength', 'Mobility'],
                    ['Thursday', 'Hybrid', 'Strength', 'Engine', 'Strength'],
                    ['Friday', 'Strength', 'Mobility', 'Hybrid', 'Engine'],
                    ['Saturday', '09:00 Engine', '10:15 Strength', '11:30 Hybrid', '—'],
                    ['Sunday', '09:30 Mobility', '10:45 Strength', '—', '—'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Inside a RALLY session',
                'main' => false,
                'files' => [
                    ['id' => $this->slideImg( 'warmup' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'athlete' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'community' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Training questions',
                'items' => [
                    ['title' => 'Do sessions repeat?', 'text' => 'The main training themes repeat through a six-week block so you can practise, measure change, and build rather than start over each class.'],
                    ['title' => 'Can I combine all four formats?', 'text' => 'Yes. Many members use Strength and Engine as anchors, then add Hybrid or Mobility according to energy, goals, and schedule.'],
                    ['title' => 'What if I am training for an event?', 'text' => 'Tell a coach the date and demands. We can help you place RALLY sessions around running, cycling, competition, or sport practice.'],
                    ['title' => 'Are there open-gym hours?', 'text' => 'The studio is coach-led. Limited open-floor blocks are available to experienced members with an agreed training plan.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Do the work. Keep the momentum.',
                'subtitle' => 'First class · €15',
                'text' => 'Choose a session with a coach and leave knowing what should come next.',
                'url' => '/start',
                'button' => 'Book your first class',
                'url-alternative' => '/studio-guide',
                'button-alternative' => 'Read the studio guide',
            ]],
        ], $home );

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
     * Creates a closing call to action for a journal article.
     *
     * @param string $title Hero title
     * @param string $text Hero text
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'RALLY Training Club',
            'text' => $text,
            'url' => '/start',
            'button' => 'Book your first class',
            'url-alternative' => '/journal',
            'button-alternative' => 'Back to field notes',
        ]];
    }


    /**
     * Creates the shared RALLY footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Train', 'text' => "- [Sessions](/training)\n- [Coaches](/coaches)\n- [Membership](/membership)\n- [Book a first class](/start)"],
                ['title' => 'Prepare', 'text' => "- [Studio guide](/studio-guide)\n- [Your first session](/studio-guide/first-session)\n- [Booking and recovery](/studio-guide/booking-and-recovery)"],
                ['title' => 'Read', 'text' => "- [RALLY Field Notes](/journal)\n- [Strength is a skill](/strength-is-a-skill)\n- [Build a week that holds](/how-to-build-a-week-that-holds)"],
                ['title' => 'Studio', 'text' => "- Köpenicker Straße 84\n- 10997 Berlin\n- [hello@rally.example](mailto:hello@rally.example)\n- +49 30 555 0184"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'RALLY footer',
                'data' => ['type' => 'cards', 'data' => ['title' => 'RALLY Training Club', 'cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'RALLY footer',
                    'data' => ['title' => 'RALLY Training Club', 'cards' => $cards],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary RALLY image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'athlete' );
    }


    /**
     * Creates the RALLY home page and returns it.
     *
     * @param string $journalId Journal page ID referenced by listing elements
     * @return Page Home page
     */
    protected function home( string $journalId ) : Page
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
                'title' => 'Train with intent. Leave with momentum.',
                'subtitle' => 'RALLY Training Club · Berlin',
                'text' => 'Coached strength and conditioning for people who want serious progress, clear direction, and enough energy left for the rest of life.',
                'url' => '/start',
                'button' => 'Book your first class',
                'url-alternative' => '/training',
                'button-alternative' => 'See how we train',
                'files' => [
                    ['id' => $this->img( 'athlete' ), 'type' => 'file'],
                    ['id' => $this->img( 'community' ), 'type' => 'file'],
                    ['id' => $this->img( 'interval' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'One week. Four ways to move.',
                'columns' => 4,
                'cards' => [
                    ['title' => 'Strength', 'text' => "Build the positions, control, and load that make everything else more capable.\n\n[Explore RALLY Strength](/training#sessions)", 'file' => ['id' => $this->img( 'detail' ), 'type' => 'file']],
                    ['title' => 'Engine', 'text' => "Develop pace you can repeat, from steady aerobic work to purposeful intervals.\n\n[Explore RALLY Engine](/training#sessions)", 'file' => ['id' => $this->img( 'run' ), 'type' => 'file']],
                    ['title' => 'Hybrid', 'text' => "Connect strength and conditioning without turning the session into chaos.\n\n[Explore RALLY Hybrid](/training#sessions)", 'file' => ['id' => $this->img( 'battle-rope' ), 'type' => 'file']],
                    ['title' => 'Mobility', 'text' => "Create useful range, better options, and a lower gear for demanding weeks.\n\n[Explore RALLY Mobility](/training#sessions)", 'file' => ['id' => $this->img( 'mobility' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'coach' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Energy in the room. Attention on the individual.\n\nRALLY sessions have music, pace, and people working side by side. They also have a plan. Coaches cap every class at fourteen, explain the intent before the clock starts, and give you an option that belongs in your training—not someone else's.\n\nYou will know what you are doing, why it matters, and what to aim for next time.\n\n[Meet the coaching team](/coaches)",
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Momentum looks different on everyone',
                'items' => [
                    ['name' => 'Marta K.', 'role' => 'Product designer', 'text' => 'I came for harder training. I stayed because the coaches made consistency feel more valuable than punishment.'],
                    ['name' => 'Leon B.', 'role' => 'First-time half marathoner', 'text' => 'Engine taught me how different paces should feel. My running became calmer before it became faster.'],
                    ['name' => 'Noor A.', 'role' => 'RALLY 8 member', 'text' => 'The room is energetic, but I never feel lost in it. Someone always knows what I am working on.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'floor' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Built for the work\n\nA bright 420 m² training floor. Eleven lifting stations. Rowers, bikes, sled lanes, and space to move without negotiating for equipment. Showers, lockers, towels, filtered water, and coffee that is better than gym coffee needs to be.\n\nThe studio sits five minutes from Schlesisches Tor, with early sessions before work and a full evening schedule.\n\n[Plan your first visit](/studio-guide)",
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the coaches',
                'layout' => 'cards',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before your first RALLY',
                'items' => [
                    ['title' => 'Do I need group-training experience?', 'text' => 'No. First-time members receive a coach check-in before class, and every session includes clear movement options.'],
                    ['title' => 'How fit do I need to be?', 'text' => 'Fit enough to begin is enough. Load, pace, range, and complexity meet you where you are.'],
                    ['title' => 'What does the first class cost?', 'text' => 'Your first regular group session is €15. There is no joining fee or obligation to choose a membership afterwards.'],
                    ['title' => 'Can I train around an injury?', 'text' => 'Often, yes, but the right answer depends on your situation. Tell us before class and follow the advice of your qualified healthcare professional.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your next training week can start today.',
                'subtitle' => 'First class · €15',
                'text' => 'Tell us what you want to build. We will help you choose the session and take care of the first step.',
                'url' => '/start',
                'button' => 'Start training',
                'url-alternative' => '/membership',
                'button-alternative' => 'Compare memberships',
            ]],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'RALLY is a high-energy Berlin training club for coached strength, conditioning, hybrid, and mobility sessions in small groups.',
                'keywords' => 'RALLY Training Club, Berlin fitness studio, strength training, conditioning classes, small group training',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'RALLY Training Club | Train With Intent',
                'description' => 'Coached strength and conditioning with serious energy, clear direction, and room to progress.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'RALLY Training Club | Train With Intent',
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
                'title' => 'RALLY Training Club | Train With Intent',
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
            'published' => true,
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
     * Creates the RALLY SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 350 72" role="img" aria-labelledby="title desc">
  <title id="title">RALLY Training Club logo</title>
  <desc id="desc">RALLY wordmark with three forward-moving orange bars</desc>
  <g fill="none" fill-rule="evenodd">
    <path d="M8 12h42L38 30H8zM8 33h31L27 51H8zM8 54h20l-8 12H8z" fill="#FF4500"/>
    <text x="66" y="49" fill="#F4F4F4" font-family="Arial Black, Archivo Black, ui-sans-serif, sans-serif" font-size="43" font-weight="900" letter-spacing="-1.5">RALLY</text>
    <text x="226" y="48" fill="#FF4500" font-family="Arial, ui-sans-serif, sans-serif" font-size="12" font-weight="700" letter-spacing="2">TRAINING CLUB</text>
  </g>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/rally-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'RALLY Training Club logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'RALLY wordmark with three forward-moving orange bars'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->logoFile = (string) $file->refresh()->id;
        }

        return $this->logoFile;
    }


    /**
     * Creates a Bold demo page below the given parent and returns it.
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
                'keywords' => 'RALLY Training Club, Berlin fitness studio, strength training, conditioning, coached group training',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

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
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the Bold fitness-studio demo page tree.
     */
    protected function pages() : void
    {
        $journalId = (string) Str::uuid7();
        $home = $this->home( $journalId );

        $this->addTraining( $home )
            ->addCoaches( $home )
            ->addMembership( $home )
            ->addBlog( $home, $journalId )
            ->addDocs( $home )
            ->addStart( $home );
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
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->slideImages[$key] = (string) $file->refresh()->id;
        }

        return $this->slideImages[$key];
    }
}
