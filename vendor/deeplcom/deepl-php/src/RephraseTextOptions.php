<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

class RephraseTextOptions extends TranslateTextOptions
{
    /** Sets the style the improved text should be in. Note that currently, only
     * a style OR a tone is supported.
     * - 'academic': Academic writing style
     * - 'business': Business writing style
     * - 'casual': Casual writing style
     * - 'default': Default writing style
     * - 'prefer_academic': Use academic style if available, otherwise default
     * - 'prefer_business': Use business style if available, otherwise default
     * - 'prefer_casual': Use casual style if available, otherwise default
     * - 'prefer_simple': Use simple style if available, otherwise default
     * - 'simple': Simple writing style
     */
    public const WRITING_STYLE = 'writing_style';

    /** Sets the tone the improved text should be in. Note that currently, only
     * a style OR a tone is supported.
     * - 'confident': Confident tone
     * - 'default': Default tone
     * - 'diplomatic': Diplomatic tone
     * - 'enthusiastic': Enthusiastic tone
     * - 'friendly': Friendly tone
     * - 'prefer_confident': Use confident tone if available, otherwise default
     * - 'prefer_diplomatic': Use diplomatic tone if available, otherwise default
     * - 'prefer_enthusiastic': Use enthusiastic tone if available, otherwise default
     * - 'prefer_friendly': Use friendly tone if available, otherwise default
     */
    public const TONE = 'tone';
}
