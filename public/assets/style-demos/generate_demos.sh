#!/bin/bash
# Generate 3-second demo videos for each of the 10 ad video styles
# Input: sample.png (1024x1024 product image)
# Output: 1080x1920 vertical MP4 demos

FFMPEG="/opt/homebrew/bin/ffmpeg"
DIR="$(cd "$(dirname "$0")" && pwd)"
IMG="$DIR/sample.png"
DUR=4
FPS=30
TOTAL_FRAMES=$((DUR * FPS))
SIZE="1080x1920"

echo "Generating 10 style demos from: $IMG"
echo "Output: ${SIZE} @ ${FPS}fps, ${DUR}s each"
echo ""

# ═══════════════════════════════════════════
# 1. CINEMATIC (Ken Burns + Letterbox + Warm)
# ═══════════════════════════════════════════
echo "1/10: Cinematic..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=1400:-1,zoompan=z='min(zoom+0.0008,1.3)':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=${SIZE}:fps=${FPS},fade=t=in:st=0:d=1.5,fade=t=out:st=$((DUR-1)):d=1,drawbox=x=0:y=0:w=iw:h=ih*0.06:color=black@0.9:t=fill,drawbox=x=0:y=ih-ih*0.06:w=iw:h=ih*0.06:color=black@0.9:t=fill,colorbalance=rs=0.08:gs=-0.02:bs=-0.06" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/cinematic.mp4" 2>/dev/null
echo "  ✓ cinematic.mp4"

# ═══════════════════════════════════════════
# 2. PARALLAX 2.5D (Horizontal pan + subtle zoom)
# ═══════════════════════════════════════════
echo "2/10: Parallax..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=2160:-1,zoompan=z='1.2':x='if(lte(on,${TOTAL_FRAMES}/2),on*4,(${TOTAL_FRAMES}-on)*4)':y='ih/2-(ih/zoom/2)':d=1:s=${SIZE}:fps=${FPS},fade=t=in:st=0:d=0.8,fade=t=out:st=$((DUR-1)):d=1" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/parallax.mp4" 2>/dev/null
echo "  ✓ parallax.mp4"

# ═══════════════════════════════════════════
# 3. SOCIAL POP (Rapid zoom bursts)
# ═══════════════════════════════════════════
echo "3/10: Social Pop..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=2400:-1,zoompan=z='if(between(on,0,${FPS}),1.0+on*0.02,if(between(on,${FPS},${FPS}*2),2.0-mod(on,${FPS})*0.015,if(between(on,${FPS}*2,${FPS}*3),1.2+mod(on,${FPS})*0.01,1.5-mod(on,${FPS})*0.005)))':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=${SIZE}:fps=${FPS},fade=t=in:st=0:d=0.3,fade=t=out:st=$((DUR-1)):d=0.5,eq=brightness=0.04:saturation=1.3" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/social_pop.mp4" 2>/dev/null
echo "  ✓ social_pop.mp4"

# ═══════════════════════════════════════════
# 4. SPOTLIGHT REVEAL (Dark to bright reveal)
# ═══════════════════════════════════════════
echo "4/10: Spotlight..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=1400:-1,zoompan=z='min(zoom+0.0005,1.15)':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=${SIZE}:fps=${FPS},eq=brightness='if(lt(t,2),-0.4+t*0.25,0.1)':contrast='if(lt(t,2),0.7+t*0.2,1.1)',fade=t=in:st=0:d=2,vignette='PI/4+t*0.05'" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/spotlight.mp4" 2>/dev/null
echo "  ✓ spotlight.mp4"

# ═══════════════════════════════════════════
# 5. DYNAMIC SHOWCASE (Multi-crop rotation)
# ═══════════════════════════════════════════
echo "5/10: Dynamic Showcase..."
SCENE_DUR=1
# Generate 4 scenes with different crops, then concat
for i in 1 2 3 4; do
  case $i in
    1) ZOOM="1.6"; PX="0"; PY="0" ;;
    2) ZOOM="1.6"; PX="iw-iw/zoom"; PY="0" ;;
    3) ZOOM="1.6"; PX="0"; PY="ih-ih/zoom" ;;
    4) ZOOM="1.0"; PX="iw/2-(iw/zoom/2)"; PY="ih/2-(ih/zoom/2)" ;;
  esac
  $FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $SCENE_DUR \
    -vf "scale=2160:-1,zoompan=z='${ZOOM}':x='${PX}':y='${PY}':d=1:s=${SIZE}:fps=${FPS},fade=t=in:st=0:d=0.3,fade=t=out:st=0.7:d=0.3" \
    -c:v libx264 -pix_fmt yuv420p -an "$DIR/_ds_${i}.mp4" 2>/dev/null
done
# Concat
echo "file '_ds_1.mp4'" > "$DIR/_ds_list.txt"
echo "file '_ds_2.mp4'" >> "$DIR/_ds_list.txt"
echo "file '_ds_3.mp4'" >> "$DIR/_ds_list.txt"
echo "file '_ds_4.mp4'" >> "$DIR/_ds_list.txt"
$FFMPEG -y -f concat -safe 0 -i "$DIR/_ds_list.txt" -c copy "$DIR/dynamic_showcase.mp4" 2>/dev/null
rm -f "$DIR"/_ds_*.mp4 "$DIR/_ds_list.txt"
echo "  ✓ dynamic_showcase.mp4"

# ═══════════════════════════════════════════
# 6. PRODUCT FOCUS (Gradient BG + centered + float)
# ═══════════════════════════════════════════
echo "6/10: Product Focus..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=1400:-1,zoompan=z='1.05+0.03*sin(on*0.05)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)+15*sin(on*0.08)':d=1:s=${SIZE}:fps=${FPS},fade=t=in:st=0:d=1,fade=t=out:st=$((DUR-1)):d=1,vignette=PI/3" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/product_focus.mp4" 2>/dev/null
echo "  ✓ product_focus.mp4"

# ═══════════════════════════════════════════
# 7. PULSE & GLOW (Pulsing zoom + edge glow)
# ═══════════════════════════════════════════
echo "7/10: Pulse & Glow..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=1800:-1,zoompan=z='1.1+0.08*sin(on*0.1)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=${SIZE}:fps=${FPS},unsharp=7:7:2.5:7:7:0,fade=t=in:st=0:d=0.5,fade=t=out:st=$((DUR-1)):d=1,eq=brightness=0.03:saturation=1.2" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/pulse_glow.mp4" 2>/dev/null
echo "  ✓ pulse_glow.mp4"

# ═══════════════════════════════════════════
# 8. SPLIT SCREEN (Left color block + right product)
# ═══════════════════════════════════════════
echo "8/10: Split Screen..."
$FFMPEG -y -f lavfi -i "color=c=#6366f1:s=540x1920:d=${DUR}:r=${FPS}" \
  -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -filter_complex "[1:v]scale=800:-1,zoompan=z='min(zoom+0.0008,1.2)':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=540x1920:fps=${FPS}[prod];[0:v][prod]hstack=inputs=2,fade=t=in:st=0:d=1,fade=t=out:st=$((DUR-1)):d=1" \
  -c:v libx264 -pix_fmt yuv420p -t $DUR -an "$DIR/split_screen.mp4" 2>/dev/null
echo "  ✓ split_screen.mp4"

# ═══════════════════════════════════════════
# 9. COLOR SWEEP (Hue rotation + vibrant)
# ═══════════════════════════════════════════
echo "9/10: Color Sweep..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=1400:-1,zoompan=z='min(zoom+0.0006,1.2)':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=${SIZE}:fps=${FPS},hue=H=t*30:s=1.5,fade=t=in:st=0:d=1,fade=t=out:st=$((DUR-1)):d=1,eq=brightness=0.05" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/color_sweep.mp4" 2>/dev/null
echo "  ✓ color_sweep.mp4"

# ═══════════════════════════════════════════
# 10. FLASH SALE (Rapid strobe cuts + urgent)
# ═══════════════════════════════════════════
echo "10/10: Flash Sale..."
$FFMPEG -y -loop 1 -framerate $FPS -i "$IMG" -t $DUR \
  -vf "scale=2400:-1,zoompan=z='if(lt(mod(on,${FPS}),${FPS}/2),1.0+mod(on,${FPS})*0.03,1.8-mod(on,${FPS})*0.02)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=${SIZE}:fps=${FPS},fade=t=in:st=0:d=0.2,fade=t=out:st=$((DUR-1)):d=0.3,eq=brightness=0.06:contrast=1.2:saturation=1.4,drawbox=x=0:y=ih*0.88:w=iw:h=ih*0.12:color=#dc2626@0.75:t=fill" \
  -c:v libx264 -pix_fmt yuv420p -an "$DIR/flash_sale.mp4" 2>/dev/null
echo "  ✓ flash_sale.mp4"

echo ""
echo "═══════════════════════════════"
echo "All 10 demos generated!"
ls -lh "$DIR"/*.mp4
