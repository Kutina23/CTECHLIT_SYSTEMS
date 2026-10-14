(function(global) {
    'use strict';

    const VoiceOrb = function() {
        this.canvas = null;
        this.gl = null;
        this.program = null;
        this.uniforms = {};
        this.animationId = null;
        this.startTime = performance.now() * 0.001;
        this.voiceLevel = 0;
        this.targetVoiceLevel = 0;
        this.rotation = 0;
        this.hue = 0;
        this.active = false;
        this.phase = 'idle';
    };

    VoiceOrb.prototype.init = function(canvas) {
        this.canvas = canvas;
        this.gl = canvas.getContext('webgl', { alpha: true, premultipliedAlpha: false, antialias: true }) ||
                  canvas.getContext('experimental-webgl', { alpha: true, premultipliedAlpha: false, antialias: true });

        if (!this.gl) {
            console.warn('VoiceOrb: WebGL not supported');
            return false;
        }

        const gl = this.gl;
        gl.clearColor(0, 0, 0, 0);
        gl.enable(gl.BLEND);
        gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

        const vert = `
            attribute vec2 position;
            attribute vec2 uv;
            varying vec2 vUv;
            void main() {
                vUv = uv;
                gl_Position = vec4(position, 0.0, 1.0);
            }
        `;

        const frag = `
            precision highp float;
            uniform float iTime;
            uniform vec3 iResolution;
            uniform float hue;
            uniform float hover;
            uniform float rot;
            uniform float hoverIntensity;
            varying vec2 vUv;

            vec3 rgb2yiq(vec3 c) {
                float y = dot(c, vec3(0.299, 0.587, 0.114));
                float i = dot(c, vec3(0.596, -0.274, -0.322));
                float q = dot(c, vec3(0.211, -0.523, 0.312));
                return vec3(y, i, q);
            }

            vec3 yiq2rgb(vec3 c) {
                float r = c.x + 0.956 * c.y + 0.621 * c.z;
                float g = c.x - 0.272 * c.y - 0.647 * c.z;
                float b = c.x - 1.106 * c.y + 1.703 * c.z;
                return vec3(r, g, b);
            }

            vec3 adjustHue(vec3 color, float hueDeg) {
                float hueRad = hueDeg * 3.14159265 / 180.0;
                vec3 yiq = rgb2yiq(color);
                float cosA = cos(hueRad);
                float sinA = sin(hueRad);
                float i = yiq.y * cosA - yiq.z * sinA;
                float q = yiq.y * sinA + yiq.z * cosA;
                yiq.y = i;
                yiq.z = q;
                return yiq2rgb(yiq);
            }

            vec3 hash33(vec3 p3) {
                p3 = fract(p3 * vec3(0.1031, 0.11369, 0.13787));
                p3 += dot(p3, p3.yxz + 19.19);
                return -1.0 + 2.0 * fract(vec3(
                    p3.x + p3.y,
                    p3.x + p3.z,
                    p3.y + p3.z
                ) * p3.zyx);
            }

            float snoise3(vec3 p) {
                const float K1 = 0.333333333;
                const float K2 = 0.166666667;
                vec3 i = floor(p + (p.x + p.y + p.z) * K1);
                vec3 d0 = p - (i - (i.x + i.y + i.z) * K2);
                vec3 e = step(vec3(0.0), d0 - d0.yzx);
                vec3 i1 = e * (1.0 - e.zxy);
                vec3 i2 = 1.0 - e.zxy * (1.0 - e);
                vec3 d1 = d0 - (i1 - K2);
                vec3 d2 = d0 - (i2 - K1);
                vec3 d3 = d0 - 0.5;
                vec4 h = max(0.6 - vec4(
                    dot(d0, d0),
                    dot(d1, d1),
                    dot(d2, d2),
                    dot(d3, d3)
                ), 0.0);
                vec4 n = h * h * h * h * vec4(
                    dot(d0, hash33(i)),
                    dot(d1, hash33(i + i1)),
                    dot(d2, hash33(i + i2)),
                    dot(d3, hash33(i + 1.0))
                );
                return dot(vec4(31.316), n);
            }

            vec4 extractAlpha(vec3 colorIn) {
                float a = max(max(colorIn.r, colorIn.g), colorIn.b);
                return vec4(colorIn.rgb / (a + 1e-5), a);
            }

            const vec3 baseColor1 = vec3(0.611765, 0.262745, 0.996078);
            const vec3 baseColor2 = vec3(0.298039, 0.760784, 0.913725);
            const vec3 baseColor3 = vec3(0.062745, 0.078431, 0.600000);
            const float innerRadius = 0.6;
            const float noiseScale = 0.65;

            float light1(float intensity, float attenuation, float dist) {
                return intensity / (1.0 + dist * attenuation);
            }

            float light2(float intensity, float attenuation, float dist) {
                return intensity / (1.0 + dist * dist * attenuation);
            }

            vec4 draw(vec2 uv) {
                vec3 color1 = adjustHue(baseColor1, hue);
                vec3 color2 = adjustHue(baseColor2, hue);
                vec3 color3 = adjustHue(baseColor3, hue);

                float ang = atan(uv.y, uv.x);
                float len = length(uv);
                float invLen = len > 0.0 ? 1.0 / len : 0.0;

                float n0 = snoise3(vec3(uv * noiseScale, iTime * 0.5)) * 0.5 + 0.5;
                float r0 = mix(mix(innerRadius, 1.0, 0.4), mix(innerRadius, 1.0, 0.6), n0);
                float d0 = distance(uv, (r0 * invLen) * uv);
                float v0 = light1(1.0, 10.0, d0);
                v0 *= smoothstep(r0 * 1.05, r0, len);
                float cl = cos(ang + iTime * 2.0) * 0.5 + 0.5;

                float a = iTime * -1.0;
                vec2 pos = vec2(cos(a), sin(a)) * r0;
                float d = distance(uv, pos);
                float v1 = light2(1.5, 5.0, d);
                v1 *= light1(1.0, 50.0, d0);

                float v2 = smoothstep(1.0, mix(innerRadius, 1.0, n0 * 0.5), len);
                float v3 = smoothstep(innerRadius, mix(innerRadius, 1.0, 0.5), len);

                vec3 col = mix(color1, color2, cl);
                col = mix(color3, col, v0);
                col = (col + v1) * v2 * v3;
                col = clamp(col, 0.0, 1.0);

                return extractAlpha(col);
            }

            vec4 mainImage(vec2 fragCoord) {
                vec2 center = iResolution.xy * 0.5;
                float size = min(iResolution.x, iResolution.y);
                vec2 uv = (fragCoord - center) / size * 2.0;

                float angle = rot;
                float s = sin(angle);
                float c = cos(angle);
                uv = vec2(c * uv.x - s * uv.y, s * uv.x + c * uv.y);

                uv.x += hover * hoverIntensity * 0.1 * sin(uv.y * 10.0 + iTime);
                uv.y += hover * hoverIntensity * 0.1 * sin(uv.x * 10.0 + iTime);

                return draw(uv);
            }

            void main() {
                vec2 fragCoord = vUv * iResolution.xy;
                vec4 col = mainImage(fragCoord);
                gl_FragColor = vec4(col.rgb * col.a, col.a);
            }
        `;

        const vs = this.compileShader(gl.VERTEX_SHADER, vert);
        const fs = this.compileShader(gl.FRAGMENT_SHADER, frag);
        if (!vs || !fs) return false;

        this.program = gl.createProgram();
        gl.attachShader(this.program, vs);
        gl.attachShader(this.program, fs);
        gl.linkProgram(this.program);

        if (!gl.getProgramParameter(this.program, gl.LINK_STATUS)) {
            console.error('VoiceOrb: program link failed:', gl.getProgramInfoLog(this.program));
            return false;
        }

        gl.useProgram(this.program);

        const vertices = new Float32Array([
            -1, -1, 0, 0,
             1, -1, 1, 0,
            -1,  1, 0, 1,
             1,  1, 1, 1,
        ]);

        const buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, vertices, gl.STATIC_DRAW);

        const positionLoc = gl.getAttribLocation(this.program, 'position');
        const uvLoc = gl.getAttribLocation(this.program, 'uv');
        gl.enableVertexAttribArray(positionLoc);
        gl.vertexAttribPointer(positionLoc, 2, gl.FLOAT, false, 16, 0);
        gl.enableVertexAttribArray(uvLoc);
        gl.vertexAttribPointer(uvLoc, 2, gl.FLOAT, false, 16, 8);

        this.uniforms = {
            iTime: gl.getUniformLocation(this.program, 'iTime'),
            iResolution: gl.getUniformLocation(this.program, 'iResolution'),
            hue: gl.getUniformLocation(this.program, 'hue'),
            hover: gl.getUniformLocation(this.program, 'hover'),
            rot: gl.getUniformLocation(this.program, 'rot'),
            hoverIntensity: gl.getUniformLocation(this.program, 'hoverIntensity'),
        };

        this.resize();
        this.startTime = performance.now() * 0.001;
        this.active = true;
        this.render();
        return true;
    };

    VoiceOrb.prototype.compileShader = function(type, source) {
        const gl = this.gl;
        const shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            console.error('VoiceOrb: shader compile failed:', gl.getShaderInfoLog(shader));
            gl.deleteShader(shader);
            return null;
        }
        return shader;
    };

    VoiceOrb.prototype.resize = function() {
        if (!this.canvas || !this.gl) return;
        const dpr = window.devicePixelRatio || 1;
        const width = this.canvas.clientWidth;
        const height = this.canvas.clientHeight;
        if (width === 0 || height === 0) return;
        this.canvas.width = width * dpr;
        this.canvas.height = height * dpr;
        this.gl.viewport(0, 0, this.canvas.width, this.canvas.height);
    };

    VoiceOrb.prototype.setPhase = function(phase) {
        this.phase = phase;
    };

    VoiceOrb.prototype.setVoiceLevel = function(level) {
        this.targetVoiceLevel = Math.max(0, Math.min(1, level));
    };

    VoiceOrb.prototype.setHue = function(h) {
        this.hue = h;
    };

    VoiceOrb.prototype.render = function() {
        if (!this.active || !this.gl || !this.program) return;
        const gl = this.gl;

        this.animationId = requestAnimationFrame(this.render.bind(this));

        const now = performance.now() * 0.001;
        const t = now - this.startTime;

        const lerpFactor = 0.15;
        this.voiceLevel += (this.targetVoiceLevel - this.voiceLevel) * lerpFactor;

        let rotationSpeed = 0.3;
        let hoverValue = 0;
        let hoverIntensity = 0;

        if (this.phase === 'listening' || this.phase === 'wake') {
            rotationSpeed = 0.3 + this.voiceLevel * 2.0;
            hoverValue = Math.min(this.voiceLevel * 2.0, 1.0);
            hoverIntensity = Math.min(this.voiceLevel * 0.8, 0.8);
        } else if (this.phase === 'speaking') {
            rotationSpeed = 0.2 + this.voiceLevel * 1.5;
            hoverValue = Math.min(this.voiceLevel * 1.5, 0.8);
            hoverIntensity = Math.min(this.voiceLevel * 0.6, 0.6);
        } else if (this.phase === 'thinking') {
            rotationSpeed = 0.15;
            hoverValue = 0.1;
            hoverIntensity = 0.2;
        }

        if (this.voiceLevel > 0.05) {
            this.rotation += 0.016 * rotationSpeed;
        }

        gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

        gl.uniform1f(this.uniforms.iTime, t);
        gl.uniform3f(this.uniforms.iResolution, this.canvas.width, this.canvas.height, this.canvas.width / this.canvas.height);
        gl.uniform1f(this.uniforms.hue, this.hue);
        gl.uniform1f(this.uniforms.hover, hoverValue);
        gl.uniform1f(this.uniforms.rot, this.rotation);
        gl.uniform1f(this.uniforms.hoverIntensity, hoverIntensity);

        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    };

    VoiceOrb.prototype.destroy = function() {
        this.active = false;
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
        if (this.gl && this.program) {
            this.gl.deleteProgram(this.program);
            this.program = null;
        }
        this.gl = null;
        this.canvas = null;
    };

    const voiceOrb = new VoiceOrb();

    global.VoiceOrb = {
        init: function(canvas) {
            return voiceOrb.init(canvas);
        },
        setPhase: function(phase) {
            voiceOrb.setPhase(phase);
        },
        setVoiceLevel: function(level) {
            voiceOrb.setVoiceLevel(level);
        },
        setHue: function(h) {
            voiceOrb.setHue(h);
        },
        resize: function() {
            voiceOrb.resize();
        },
        destroy: function() {
            voiceOrb.destroy();
        },
        getInstance: function() {
            return voiceOrb;
        }
    };

})(window);
