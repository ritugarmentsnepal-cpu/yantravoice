<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yantra Studio - UGC Editor</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800;900&display=swap');

        body {
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }

        #preview-wrapper {
            transform: scale(0.4);
            transform-origin: center center;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
        }

        #main-composition {
            width: 1080px;
            height: 1920px;
            position: relative;
            overflow: hidden;
            background-color: #000;
        }

        .track-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        /* Dynamic CSS Background Layer */
        #background-container {
            z-index: 1;
            transition: background 0.5s ease;
        }

        /* Avatar Video */
        #avatar-container {
            z-index: 2; /* Needs to sit on top of background */
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #captions-container {
            z-index: 3;
            position: absolute;
            width: 1080px;
            text-align: center;
            pointer-events: none;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            padding: 0 100px;
            box-sizing: border-box;
            /* Y-position will be set dynamically via GSAP from tokens */
            top: 80%; 
        }

        .caption-word {
            font-size: 110px;
            text-transform: uppercase;
            text-shadow: 6px 6px 0px #000000, 0px 0px 20px rgba(0,0,0,0.8);
            margin: 0 15px;
            opacity: 0;
            transform: scale(0.5);
            -webkit-text-stroke: 4px #000;
        }

        #watermark-container {
            z-index: 4;
        }

    </style>
</head>
<body>

    <div id="preview-wrapper">
        <div id="main-composition" data-composition-id="ugc-short-video">
            
            <!-- TRACK 1: Dynamic Background -->
            <div id="background-container" class="track-layer"></div>

            <!-- TRACK 2: Avatar / Presenter -->
            <div id="avatar-container" class="track-layer">
                @if(isset($job) && $job->avatar_video_path)
                    <video src="{{ asset('storage/' . $job->avatar_video_path) }}" class="avatar-img" autoplay muted loop playsinline></video>
                @else
                    <img src="https://images.unsplash.com/photo-1594824436951-7f12bc58ec53?ixlib=rb-4.0.3&auto=format&fit=crop&w=1080&q=80" class="avatar-img" alt="Avatar Placeholder" />
                @endif
            </div>

            <!-- TRACK 3: Captions -->
            <div id="captions-container" class="track-layer"></div>

            <!-- TRACK 4: Watermark Overlay (Free Users Only) -->
            @php
                $isPremium = isset($job) && $job->user && $job->user->plan === 'premium';
            @endphp
            @if(!$isPremium)
            <div id="watermark-container" class="track-layer" style="pointer-events: none;">
                <div style="position: absolute; top: 30px; right: 30px; background: rgba(0,0,0,0.5); padding: 10px 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);">
                    <span style="font-family: 'Inter', sans-serif; font-weight: 800; font-size: 24px; color: #fff; letter-spacing: 1px;">
                        MADE WITH <span style="color: #FFDE00;">YANTRA STUDIO</span>
                    </span>
                </div>
            </div>
            @endif

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const blueprint = @json($blueprintData);

            const avatarContainer = document.getElementById('avatar-container');
            const backgroundContainer = document.getElementById('background-container');
            const captionsContainer = document.getElementById('captions-container');

            document.getElementById('main-composition').setAttribute('data-duration', blueprint.video_duration);

            const mainTimeline = gsap.timeline();
            
            // Expose globally for HyperFrames
            window.__timelines = [mainTimeline];

            // Setup global caption styling vars
            let defaultTextColor = "#FFFFFF";
            let highlightTextColor = "#FFDE00";
            let animStyle = "kinetic-bounce";

            blueprint.scenes.forEach((scene) => {
                
                // --- A. Apply Design Tokens (If Provided) ---
                if (scene.design_tokens) {
                    const t = scene.design_tokens;
                    
                    // Build CSS Gradient
                    if (t.background_type === 'linear-gradient' && t.background_colors && t.background_colors.length > 0) {
                        const angle = t.gradient_angle || '180deg';
                        const colors = t.background_colors.join(', ');
                        mainTimeline.set(backgroundContainer, {
                            background: `linear-gradient(${angle}, ${colors})`
                        }, scene.start_time);
                    }

                    // Update Typography Variables
                    if(t.font_family) captionsContainer.style.fontFamily = `"${t.font_family}", sans-serif`;
                    if(t.font_weight) captionsContainer.style.fontWeight = t.font_weight;
                    if(t.text_color_primary) defaultTextColor = t.text_color_primary;
                    if(t.text_color_highlight) highlightTextColor = t.text_color_highlight;
                    if(t.text_animation_style) animStyle = t.text_animation_style;
                    
                    // Map Vertical Position (e.g. "80%") to top property
                    if(t.caption_position_y) {
                        mainTimeline.set(captionsContainer, { top: t.caption_position_y }, scene.start_time);
                    }
                }

                // --- B. Avatar Transform (Jump Cuts) ---
                mainTimeline.set(avatarContainer, {
                    scale: scene.avatar_transform.scale,
                    x: scene.avatar_transform.position.x,
                    y: scene.avatar_transform.position.y
                }, scene.start_time);

                // --- C. Captions (Kinetic Typography) ---
                if (scene.captions && scene.captions.length > 0) {
                    const sceneWords = [];

                    scene.captions.forEach((caption) => {
                        const wordEl = document.createElement('span');
                        wordEl.className = 'caption-word';
                        wordEl.innerText = caption.word;
                        wordEl.style.color = defaultTextColor;
                        
                        captionsContainer.appendChild(wordEl);
                        sceneWords.push(wordEl);

                        // Base Entry Animation
                        if(animStyle === 'kinetic-bounce') {
                            mainTimeline.fromTo(wordEl, 
                                { opacity: 0, scale: 0.2, rotation: -10 },
                                { opacity: 1, scale: 1, rotation: 0, duration: 0.2, ease: "back.out(2)" },
                                caption.start
                            );
                        } else {
                            // Minimal pop
                            mainTimeline.fromTo(wordEl, 
                                { opacity: 0, y: 30 },
                                { opacity: 1, y: 0, duration: 0.2, ease: "power2.out" },
                                caption.start
                            );
                        }

                        // Highlight Active Word
                        mainTimeline.to(wordEl, {
                            color: highlightTextColor,
                            duration: 0.1
                        }, caption.start + 0.1);

                        // Exit Animation
                        mainTimeline.to(wordEl, {
                            opacity: 0,
                            y: -20,
                            duration: 0.2
                        }, caption.end + 0.5); 
                    });

                    mainTimeline.set(sceneWords, { display: 'none' }, scene.start_time + scene.duration);
                }
            });
            
            // Repeat for local dev preview
            // Note: HyperFrames renderer normally plays it once and records
            mainTimeline.repeat(-1);
        });
    </script>
</body>
</html>
