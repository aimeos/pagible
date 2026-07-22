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
 * Paper theme demo for the Margin & Matter independent journal.
 */
class PaperDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'about' => 'Meet the editors and contributors behind Margin & Matter, an independent journal of design, craft, place, and working life.',
        'a-chair-made-for-the-next-owner' => 'A visit to a furniture workshop where repairable joints, replaceable parts, and patient materials make a chair ready for its next owner.',
        'contribute' => 'Contributor guidance for writers, photographers, and illustrators who want to work with Margin & Matter.',
        'contribute/pitching-a-story' => 'How to pitch a clear, reported story to Margin & Matter, including the details editors need and what happens after submission.',
        'journal' => 'Read Margin & Matter stories about useful objects, thoughtful rooms, public places, and the people who shape them.',
        'rooms-that-teach-us-how-to-look' => 'What a small coastal library reveals about daylight, attention, and designing public rooms around the act of reading.',
        'subscribe' => 'Subscribe to Margin & Matter online or in print and receive each quarterly issue without advertising or tracking.',
        'the-night-bus-as-a-public-room' => 'An overnight journey across the city considers the night bus as shared shelter, workplace, and one of the last truly public rooms.',
        'why-good-tools-grow-quiet' => 'Why the tools we keep are often the ones that recede from view, improve through use, and leave room for judgement.',
    ];

    /**
     * Curated Unsplash photos used across the journal demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'archive' => ['photo-1524995997946-a1c2e315a42f', 'Journal archive', 'Tall library shelves holding an extensive collection of books and journals'],
        'chair' => ['photo-1503602642458-232111445657', 'Repairable wooden chairs', 'A row of carefully made wooden chairs in a quiet interior'],
        'city' => ['photo-1477959858617-67f85cf4f1df', 'City after dark', 'Dense city streets and buildings seen in the blue light of evening'],
        'craft' => ['photo-1452860606245-08befc0ff44b', 'Tools at the workbench', 'Hand tools and materials arranged on a working craft bench'],
        'desk' => ['photo-1499750310107-5fef28a66643', 'Editor desk', 'Notebook, keyboard, camera, and coffee arranged on an editor desk'],
        'home' => ['photo-1487958449943-2429e8be8625', 'House shaped by daylight', 'A restrained modern house with large windows and a sheltered terrace'],
        'library' => ['photo-1507842217343-583bb7270b66', 'Reading room', 'A quiet reading room lined with books from floor to ceiling'],
        'notebook' => ['photo-1455390582262-044cdead277a', 'Reporter notebook', 'Open notebook and pen prepared for an interview'],
        'print' => ['photo-1543002588-bfa74002ed7e', 'Printed journal', 'A considered stack of printed books and independent magazines'],
        'studio' => ['photo-1497366754035-f200968a6e72', 'Editorial studio', 'Bright shared studio with tables, shelves, and plants'],
        'tools' => ['photo-1450101499163-c8848c66ca85', 'Marked-up pages', 'Printed pages, notes, and a pen spread across a working table'],
        'transit' => ['photo-1519608487953-e999c86e7455', 'Night journey', 'City lights passing beyond a window during an evening journey'],
    ];

    private string $element;
    private string $logoFile;
    /** @var array<string, string> File IDs for fixed-ratio slideshow images */
    private array $slideImages = [];


    /**
     * Creates the About page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAbout( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'About',
            'title' => 'About Margin & Matter',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Look closely. Stay awhile.',
                'subtitle' => 'About the journal',
                'text' => 'Margin & Matter is an independent quarterly about the objects, rooms, and public places that shape ordinary life. We publish reported stories for readers who value detail over novelty.',
                'files' => [['id' => $this->img( 'studio' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'notebook' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## A journal made in the field\n\nEvery story begins away from the desk: in a workshop, a reading room, a kitchen, on a late bus, or beside someone who knows a place through daily use. Reporting gives the journal its texture.\n\nWe return to the studio with notes, photographs, measurements, and questions. The finished piece should carry the grain of that encounter without pretending the writer was never there.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Our editorial measure',
                'cards' => [
                    ['title' => 'Specific before sweeping', 'text' => 'A well-observed room, tool, or routine can hold a larger argument without announcing one too soon.'],
                    ['title' => 'Useful expertise', 'text' => 'We ask practitioners how things work, what they cost, where they fail, and what experience has changed.'],
                    ['title' => 'A lasting page', 'text' => 'We edit for clarity and rhythm, then publish without pop-ups, autoplay, or a race to keep the reader clicking.'],
                    ['title' => 'Room for the reader', 'text' => 'We leave space for attention, uncertainty, and conclusions that arrive through the reporting rather than before it.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'From notebook to issue',
                'files' => [
                    ['id' => $this->slideImg( 'notebook' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'tools' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'print' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Masthead',
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'header' => 'row+col',
                'table' => [
                    ['Role', 'Name', 'Based in'],
                    ['Editor', 'Clara Voss', 'Hamburg'],
                    ['Art director', 'Mina Okafor', 'London'],
                    ['Features editor', 'Jon Bell', 'Copenhagen'],
                    ['Photo editor', 'Leonie Hart', 'Berlin'],
                    ['Copy editor', 'Samir Patel', 'Leeds'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Write to the editors',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the journal and its four stories below the home page.
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
            'title' => 'Journal | Margin & Matter',
            'path' => 'journal',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Stories for a longer read',
                'subtitle' => 'Issue 07 — The useful life',
                'text' => 'Four stories about repair, attention, public space, and the quiet tools that earn a permanent place in our days.',
                'files' => [['id' => $this->img( 'archive' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'In this issue',
                'layout' => 'default',
                'limit' => 4,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Rooms that teach us how to look',
            'title' => 'Rooms That Teach Us How to Look',
            'path' => 'rooms-that-teach-us-how-to-look',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Rooms that teach us how to look',
                "The reading room at Ravn Library is barely wider than a railway carriage. A long window faces the harbour; the shelves stop short of the ceiling; every chair can be turned without scraping the next. Nothing in the room asks to be photographed. Everything asks you to stay.\n\nArchitect Liv Andersen began with the act of reading rather than the image of a library. The building's smallest decisions follow from that distinction.",
                $this->img( 'library' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Daylight with a job to do',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'home' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "The south wall is thick enough to turn direct sun into a reflected wash. In winter, the light reaches the back tables. In summer, the deep reveal keeps glare off the page.\n\nThe effect is gentle, but not accidental. Andersen's team built a full-size section of the window before approving the glass, sill height, and pale mineral finish.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'The room in four decisions',
                'header' => 'row+col',
                'table' => [
                    ['Decision', 'What it changes'],
                    ['Low shelves at the centre', 'Readers can see the room, the harbour, and one another'],
                    ['One long shared table', 'Short visits and afternoon work feel equally welcome'],
                    ['No ceiling downlights', 'Light falls across the page instead of pooling on the floor'],
                    ['Chairs facing several directions', 'Reading can be private without making the room defensive'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The library succeeds because its architecture is present as a sequence of permissions: sit here, move that chair, follow the weather, take your time. A room can be visually quiet and still have a great deal to say.",
            ]],
            $this->articleHero( 'Continue with Issue 07', 'Read the remaining stories on objects, tools, and public places made for a longer useful life.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'A chair made for the next owner',
            'title' => 'A Chair Made for the Next Owner',
            'path' => 'a-chair-made-for-the-next-owner',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'A chair made for the next owner',
                "In the back of Mara Klein's workshop, finished chairs wait beside a drawer of spare stretchers, woven seats, and wooden pins. The parts are not leftovers. Each belongs to a repair the workshop expects to make ten or twenty years from now.\n\nKlein designs for the second owner as carefully as the first. That changes the joint, the finish, the instructions under the seat, and the promise attached to the sale.",
                $this->img( 'chair' )
            ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Built around the repair',
                'cards' => [
                    ['title' => 'Joints come apart', 'text' => 'Pinned mortise-and-tenon joints can be opened by a repairer without cutting away sound timber.', 'file' => ['id' => $this->img( 'craft' ), 'type' => 'file']],
                    ['title' => 'Surfaces can age', 'text' => 'Soap and oil finishes accept a local repair. A scratch becomes maintenance, not a reason to strip the chair.', 'file' => ['id' => $this->img( 'chair' ), 'type' => 'file']],
                    ['title' => 'Parts remain legible', 'text' => 'A mark beneath the seat records the timber, year, finish, and dimensions of replaceable pieces.', 'file' => ['id' => $this->img( 'tools' ), 'type' => 'file']],
                    ['title' => 'Knowledge stays nearby', 'text' => 'Drawings, jigs, and spare timber remain in the workshop so a future maker can repeat the repair.', 'file' => ['id' => $this->img( 'studio' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'The cost of keeping a promise',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Repairability adds work before it saves material. The workshop keeps drawings, labels batches of timber, stores jigs, and trains every new maker to reproduce old parts. None of this is visible in the showroom price tag.\n\nKlein treats that archive as part of the chair. Without it, a replaceable stretcher is only a good intention.",
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Inside the workshop',
                'files' => [
                    ['id' => $this->slideImg( 'craft' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'tools' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'chair' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'html', 'group' => 'main', 'data' => [
                'text' => '<aside><strong>Longevity is not a material.</strong> It is a relationship between an object, its owner, and the people prepared to keep it working.</aside>',
            ]],
            $this->articleHero( 'Continue with Issue 07', 'Read the remaining stories on objects, tools, and public places made for a longer useful life.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'The night bus as a public room',
            'title' => 'The Night Bus as a Public Room',
            'path' => 'the-night-bus-as-a-public-room',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'The night bus as a public room',
                "At 02:14, the N8 carries a nurse finishing a late shift, two cooks sharing chips, an airport cleaner, a student asleep against the window, and three people whose journeys are harder to guess. The bus is transport, but for forty minutes it is also shelter.\n\nCities speak often about public space and rarely include the rooms that move. At night, those rooms may be the only ones still open without asking you to buy something.",
                $this->img( 'transit' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'city' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "### A timetable is a form of access\n\nThe route crosses the same distance as it does at noon, but its meaning changes after the trains stop. A missed connection can add an hour. A well-lit stop can decide whether a passenger feels able to wait.\n\nFrequency, visibility, and a driver who pauses until someone is seated are small pieces of civic design. Together they determine who can use the city after dark.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'One route, many kinds of work',
                'header' => 'row+col',
                'table' => [
                    ['Time', 'Stop', 'What changes'],
                    ['00:42', 'Central station', 'The last train empties into the night network'],
                    ['01:18', 'Market road', 'Restaurant and warehouse shifts overlap'],
                    ['02:14', 'North hospital', 'Clinical teams change over'],
                    ['03:06', 'Airport junction', 'The first morning crews begin to travel'],
                    ['04:25', 'River estate', 'Night service gives way to the first daytime route'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Shared without being social',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Most passengers do not speak. They negotiate the room through bags moved from seats, glances toward the next stop, a charging cable offered across the aisle. The bus does not create community on demand. It does something more modest: it lets different lives occupy the same protected space for a while.\n\nThat is enough to make it a public room, and enough reason to design the service with the care we give a square or a library.",
            ]],
            $this->articleHero( 'Continue with Issue 07', 'Read the remaining stories on objects, tools, and public places made for a longer useful life.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'Why good tools grow quiet',
            'title' => 'Why Good Tools Grow Quiet',
            'path' => 'why-good-tools-grow-quiet',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Why good tools grow quiet',
                "The best tool on a copy editor's desk is a keyboard shortcut nobody notices. In a carpenter's hand, it may be a plane adjusted so well that attention moves from the blade to the grain. Familiar tools do not demand less skill. They leave more room for it.\n\nWe are used to judging new objects by what they add. Kept objects are judged by what they stop interrupting.",
                $this->img( 'desk' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Fluency has a shape',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "A quiet tool gives clear feedback and behaves consistently, but it also leaves a margin for judgement. The cook can hear when the pan is ready. The writer can change a sentence without negotiating a panel. The mechanic can reach a common fastener without dismantling a cover.\n\nEase is not the absence of thought. It is the removal of thought that does not belong to the task.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Signs of a tool worth keeping',
                'cards' => [
                    ['title' => 'Its state is visible', 'text' => 'You can tell what it is doing, what changed, and how to return to a known position.'],
                    ['title' => 'Wear improves understanding', 'text' => 'Marks reveal where hands rest, edges meet, and maintenance will eventually be needed.'],
                    ['title' => 'Skill remains portable', 'text' => 'What you learn belongs to the practice, not only to one model, account, or subscription.'],
                    ['title' => 'Maintenance is expected', 'text' => 'Cleaning, sharpening, adjustment, and repair are part of the object rather than evidence that it has failed.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'craft' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Quietness cannot be measured on first use. It appears through repetition, repair, and the gradual disappearance of small frictions. This is why reviews of new tools often miss the quality that matters most: whether the object will still make sense once its novelty has gone.",
            ]],
            $this->articleHero( 'Continue with Issue 07', 'Read the remaining stories on objects, tools, and public places made for a longer useful life.' ),
        ], $journal );

        return $this;
    }


    /**
     * Creates the contributor guide and pitching page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addContribute( Page $home ) : static
    {
        $guide = $this->page( [
            'lang' => 'en',
            'name' => 'Contribute',
            'title' => 'Contribute to Margin & Matter',
            'path' => 'contribute',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Bring us a place, an object, or a practice',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Margin & Matter commissions reported features, essays, interviews, photo stories, and short field notes. We welcome first-time contributors as well as established writers and image-makers.\n\nA good proposal begins with access: a person you can speak with, a place you can enter, a process you can observe, or an archive you can examine. Tell us what you have found and why the story needs reporting now.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What we publish',
                'cards' => [
                    ['title' => 'Reported features', 'text' => '1,800–3,000 words built from observation, interviews, and a clear central question.'],
                    ['title' => 'Field notes', 'text' => '600–1,000 words from one revealing visit, object, routine, or small piece of research.'],
                    ['title' => 'Photo essays', 'text' => 'A coherent visual argument of 10–16 images, accompanied by captions and a short introduction.'],
                    ['title' => 'Interviews', 'text' => 'Edited conversations that make a practitioner’s methods, decisions, and experience useful to the reader.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Commissioning terms',
                'header' => 'row+col',
                'table' => [
                    ['Format', 'Typical scope', 'Fee from'],
                    ['Reported feature', '1,800–3,000 words', '€900'],
                    ['Field note', '600–1,000 words', '€350'],
                    ['Interview', 'Edited conversation and introduction', '€500'],
                    ['Photo essay', '10–16 images with captions', '€800'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Rights and expenses',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "We license first publication in English, online and in one print issue, followed by a three-month period of exclusivity. Copyright remains with the contributor. Translation, syndication, and reuse are agreed separately.\n\nNecessary reporting expenses are agreed before work begins and reimbursed against receipts. We do not ask contributors to absorb travel that forms part of an approved commission.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you send a pitch',
                'items' => [
                    ['title' => 'Do you accept completed articles?', 'text' => 'We prefer pitches because reporting and scope often develop with an editor. You may attach a draft, but we cannot promise to consider it as a finished commission.'],
                    ['title' => 'Can I pitch outside Europe?', 'text' => 'Yes. The journal is edited in Europe but its subject is not bounded by geography. Local knowledge and credible access matter more than location.'],
                    ['title' => 'When will I hear back?', 'text' => 'We reply to proposals we want to discuss within three weeks. If you have not heard by then, you are free to offer the story elsewhere.'],
                    ['title' => 'Do you use generative material?', 'text' => 'No. Submitted writing, reporting, illustration, and photography must be the contributor’s own work. Research tools should be disclosed when they materially shape the piece.'],
                ],
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Pitching a story',
            'title' => 'Pitching a Story | Margin & Matter',
            'path' => 'contribute/pitching-a-story',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'A pitch is a small piece of reporting',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "A pitch should show that the story exists beyond its theme. Instead of proposing an essay about repair, introduce the repairer, object, archive, or conflict that lets a reader encounter the subject in a particular way.\n\nThree or four considered paragraphs are usually enough. Include the central question, what you already know, who you can speak with, what remains to be reported, and why you are suited to the assignment.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'What an editor needs',
                'header' => 'row+col',
                'table' => [
                    ['Part', 'Useful detail'],
                    ['Opening', 'The scene, person, or discovery that makes the story tangible'],
                    ['Question', 'The tension the reporting will examine—not only the broad topic'],
                    ['Access', 'Confirmed and prospective interviews, places, documents, and images'],
                    ['Shape', 'A likely narrative route and the approximate length'],
                    ['Contributor', 'Relevant experience and two links that show how you work'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'What happens next',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "If a pitch interests us, an editor will ask questions before offering a commission. Together you will agree the angle, approximate length, fee, expenses, deadline, and any photography or illustration. The commissioning email records those terms.\n\nDuring reporting, tell the editor early if access changes or the evidence points toward a different story. A strong commission can change shape; it should not change shape in silence.",
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Pitch the journal',
            ]],
        ], $guide );

        return $this;
    }


    /**
     * Creates the subscription page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addSubscribe( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Subscribe',
            'title' => 'Subscribe to Margin & Matter',
            'path' => 'subscribe',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Keep the journal close',
                'subtitle' => 'Four issues each year',
                'text' => 'Read every story online, receive the quarterly print edition, or help fund the reporting that keeps Margin & Matter independent.',
                'files' => [['id' => $this->img( 'print' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Choose how you read',
                'text' => 'All subscriptions renew annually. Prices include tax; print postage is added at checkout.',
                'items' => [
                    ['name' => 'Reader', 'price' => '24€', 'unit' => 'per year', 'text' => 'The complete digital journal and our monthly letter from the editors.', 'features' => "- Every story online\n- Full issue archive\n- Monthly editors’ letter\n- Cancel at any time", 'url' => '#contact', 'button' => 'Choose Reader'],
                    ['name' => 'Print', 'price' => '58€', 'unit' => 'per year', 'text' => 'Four print issues, with full digital access included throughout the year.', 'features' => "- Four quarterly issues\n- Complete digital access\n- Monthly editors’ letter\n- Subscriber cover", 'url' => '#contact', 'button' => 'Choose Print', 'highlight' => true, 'badge' => 'Most chosen'],
                    ['name' => 'Sustainer', 'price' => '96€', 'unit' => 'per year', 'text' => 'Print and digital access with additional support for independent commissions.', 'features' => "- Everything in Print\n- Annual studio note\n- Your name in the year-end issue\n- Extra support for reporting", 'url' => '#contact', 'button' => 'Become a Sustainer'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Notes from readers',
                'items' => [
                    ['name' => 'Nadia Wells', 'role' => 'Reader since Issue 02', 'text' => 'It is the rare publication I finish rather than save for later. The stories are careful, useful, and never in a hurry.'],
                    ['name' => 'Owen Lind', 'role' => 'Print subscriber', 'text' => 'The print issue stays on our studio table for months. Someone always finds a detail the rest of us missed.'],
                    ['name' => 'Aya Mensah', 'role' => 'Sustaining member', 'text' => 'I value knowing what my subscription pays for: reporting time, fair fees, good editing, and a physical issue made to keep.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Subscription questions',
                'items' => [
                    ['title' => 'Where do you deliver?', 'text' => 'We send the print journal worldwide. Postage is calculated from the delivery address before payment.'],
                    ['title' => 'When will my first issue arrive?', 'text' => 'Your subscription starts with the current issue unless you choose the next issue at checkout. Dispatch usually takes three working days.'],
                    ['title' => 'Can I give a subscription?', 'text' => 'Yes. Enter the recipient’s delivery details and choose the gift option. We will email you a printable card immediately.'],
                    ['title' => 'Can I change between print and digital?', 'text' => 'Yes. Write to subscriptions before renewal and we will move the account without creating a second subscription.'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Start or change a subscription',
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
     * Creates a closing subscription call to action for an article.
     *
     * @param string $title Hero title
     * @param string $text Hero text
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'Margin & Matter',
            'text' => $text,
            'url' => '/journal',
            'button' => 'Back to the journal',
            'url-alternative' => '/subscribe',
            'button-alternative' => 'Subscribe',
        ]];
    }


    /**
     * Creates the shared Margin & Matter footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Journal', 'text' => "- [Current issue](/journal)\n- [About the journal](/about)"],
                ['title' => 'Contribute', 'text' => "- [Contributor guide](/contribute)\n- [Pitching a story](/contribute/pitching-a-story)\n- [Write to the editors](/about#contact)"],
                ['title' => 'Subscribe', 'text' => '- [Online and print options](/subscribe)'],
                ['title' => 'Margin & Matter', 'text' => '- [editors@marginandmatter.example](mailto:editors@marginandmatter.example)'],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Margin & Matter footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Margin & Matter footer',
                    'data' => ['cards' => $cards],
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
     * Returns the ID of the primary Margin & Matter image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'desk' );
    }


    /**
     * Creates the Margin & Matter home page and returns it.
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
                'title' => 'A journal for the things worth noticing',
                'subtitle' => 'Margin & Matter — Issue 07',
                'text' => 'Independent stories about useful objects, thoughtful rooms, public places, and the people who know them closely.',
                'url' => '/journal',
                'button' => 'Read the journal',
                'url-alternative' => '/subscribe',
                'button-alternative' => 'Subscribe',
                'files' => [['id' => $fileId, 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Inside the current issue',
                'cards' => [
                    ['title' => 'Design', 'text' => "A coastal library shaped around daylight, patience, and the private act of reading.\n\n[Read the story](/rooms-that-teach-us-how-to-look)", 'file' => ['id' => $this->img( 'library' ), 'type' => 'file']],
                    ['title' => 'Craft', 'text' => "A furniture maker designs every chair with its future repair—and its next owner—in mind.\n\n[Visit the workshop](/a-chair-made-for-the-next-owner)", 'file' => ['id' => $this->img( 'chair' ), 'type' => 'file']],
                    ['title' => 'Place', 'text' => "After the trains stop, the night bus becomes transport, shelter, and one of the city's last public rooms.\n\n[Take the night route](/the-night-bus-as-a-public-room)", 'file' => ['id' => $this->img( 'transit' ), 'type' => 'file']],
                    ['title' => 'Practice', 'text' => "Why familiar tools often improve by receding from view and leaving more room for judgement.\n\n[Consider the tools we keep](/why-good-tools-grow-quiet)", 'file' => ['id' => $this->img( 'desk' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'print' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Made to be read, then kept\n\nThe print journal appears four times a year on uncoated paper, without advertising. Each issue gathers new reporting with photographs, drawings, and a short archive piece chosen for the present theme.\n\nThe website carries every story in a calm, searchable edition. Subscribers receive both the finished work and a monthly letter from the editors about what we are reading, visiting, and preparing next.\n\n[See subscription options](/subscribe)",
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From Issue 07',
                'layout' => 'cards',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Why readers keep it',
                'items' => [
                    ['name' => 'Nadia Wells', 'role' => 'Architect and reader', 'text' => 'Margin & Matter pays attention to the part of design that begins after the photograph—the way a place is actually used.'],
                    ['name' => 'Owen Lind', 'role' => 'Furniture maker', 'text' => 'The reporting is exact without becoming cold. I recognise the workshop in it, including the compromises.'],
                    ['name' => 'Aya Mensah', 'role' => 'Print subscriber', 'text' => 'An issue can sit unopened for a week without making me feel behind. When I pick it up, it rewards the time.'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Send a letter to the journal',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'Margin & Matter']],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Margin & Matter is an independent quarterly journal of design, craft, place, and working life, published online and in print.',
                'keywords' => 'independent journal, design writing, craft, architecture, public space, print magazine',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Margin & Matter | A Journal for the Things Worth Noticing',
                'description' => 'Independent stories about useful objects, thoughtful rooms, public places, and the people who know them closely.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Margin & Matter | Independent Journal',
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
                'title' => 'Margin & Matter | Independent Journal',
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
     * Creates the Margin & Matter SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 80" role="img" aria-labelledby="title desc">
  <title id="title">Margin &amp; Matter logo</title>
  <desc id="desc">Margin &amp; Matter wordmark with an open-page monogram</desc>
  <g fill="none" fill-rule="evenodd">
    <rect x="4" y="8" width="64" height="64" rx="8" fill="#1A1A1A"/>
    <path d="M18 24c8 0 14 3 18 8 4-5 10-8 18-8v32c-8 0-14 2-18 6-4-4-10-6-18-6V24Z" stroke="#F7F7F4" stroke-width="3" stroke-linejoin="round"/>
    <path d="M36 32v30" stroke="#F7F7F4" stroke-width="2"/>
    <text x="88" y="49" fill="#1A1A1A" font-family="ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" font-size="34" font-weight="500" letter-spacing="-1">MARGIN &amp; MATTER</text>
    <path d="M90 61h388" stroke="#1A1A1A" stroke-width="1"/>
  </g>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/margin-and-matter-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'Margin & Matter logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'Margin & Matter wordmark with an open-page monogram'],
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
     * Creates a Paper demo page below the given parent and returns it.
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
                'keywords' => 'Margin & Matter, independent journal, design, craft, architecture, public space',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $content[] = ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'Margin & Matter']];
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
     * Builds the Paper journal demo page tree.
     */
    protected function pages() : void
    {
        $journalId = (string) Str::uuid7();
        $home = $this->home( $journalId );

        $this->addBlog( $home, $journalId )
            ->addAbout( $home )
            ->addContribute( $home )
            ->addSubscribe( $home );
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
