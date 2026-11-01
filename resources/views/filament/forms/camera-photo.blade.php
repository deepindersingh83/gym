<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            storedUrl: @js($getStoredUrl()),
            facingMode: @js($getFacingMode()),
            stream: null,
            live: false,
            error: null,
            maxSide: 1000,

            get preview() {
                return this.state && String(this.state).startsWith('data:') ? this.state : (this.state ? this.storedUrl : null)
            },

            async start() {
                this.error = null
                if (! navigator.mediaDevices?.getUserMedia) {
                    // Older browsers / plain http: the phone's own camera app via the file input.
                    this.$refs.cameraInput.click()
                    return
                }
                try {
                    this.stop()
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: this.facingMode, width: { ideal: 1280 }, height: { ideal: 1280 } }, audio: false })
                    this.$refs.video.srcObject = this.stream
                    await this.$refs.video.play()
                    this.live = true
                } catch (e) {
                    this.error = e?.name === 'NotAllowedError'
                        ? 'Camera access was blocked. Allow the camera in your browser settings, or use Upload.'
                        : 'No camera found. Use Upload instead.'
                }
            },

            flip() {
                this.facingMode = this.facingMode === 'user' ? 'environment' : 'user'
                this.start()
            },

            capture() {
                const video = this.$refs.video
                this.state = this.toJpeg(video, video.videoWidth, video.videoHeight)
                this.stop()
            },

            stop() {
                this.stream?.getTracks().forEach((t) => t.stop())
                this.stream = null
                this.live = false
            },

            pick(event) {
                const file = event.target.files?.[0]
                event.target.value = ''
                if (! file) return
                if (! file.type.startsWith('image/')) {
                    this.error = 'Please choose an image.'
                    return
                }
                const img = new Image()
                img.onload = () => {
                    this.state = this.toJpeg(img, img.naturalWidth, img.naturalHeight)
                    URL.revokeObjectURL(img.src)
                }
                img.onerror = () => (this.error = 'That image could not be opened.')
                img.src = URL.createObjectURL(file)
            },

            toJpeg(source, width, height) {
                const scale = Math.min(1, this.maxSide / Math.max(width, height))
                const canvas = document.createElement('canvas')
                canvas.width = Math.round(width * scale)
                canvas.height = Math.round(height * scale)
                const ctx = canvas.getContext('2d')
                ctx.fillStyle = '#fff'
                ctx.fillRect(0, 0, canvas.width, canvas.height)
                ctx.drawImage(source, 0, 0, canvas.width, canvas.height)
                return canvas.toDataURL('image/jpeg', 0.85)
            },

            remove() {
                this.stop()
                this.state = null
            },

            // Alpine calls this when the field leaves the page, so the camera light goes off.
            destroy() {
                this.stop()
            },
        }"
        x-init="$el.closest('form')?.addEventListener('submit', () => stop())"
        {{ $getExtraAttributeBag() }}
    >
        <div style="display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap">
            <div style="width: 160px; height: 160px; border-radius: 16px; overflow: hidden; background: rgba(127,127,127,.12); display: grid; place-items: center; flex: none; position: relative">
                <video x-ref="video" x-show="live" playsinline muted style="width: 100%; height: 100%; object-fit: cover"></video>
                <template x-if="! live && preview">
                    <img :src="preview" alt="Photo" style="width: 100%; height: 100%; object-fit: cover">
                </template>
                <template x-if="! live && ! preview">
                    <x-filament::icon icon="heroicon-o-user-circle" style="width: 64px; height: 64px; opacity: .35" />
                </template>
            </div>

            <div style="display: grid; gap: .5rem">
                <div x-show="! live" style="display: flex; gap: .5rem; flex-wrap: wrap">
                    <x-filament::button type="button" icon="heroicon-o-camera" x-on:click="start()" :disabled="$isDisabled()">
                        Take photo
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-up-tray" x-on:click="$refs.fileInput.click()" :disabled="$isDisabled()">
                        Upload
                    </x-filament::button>
                    <x-filament::button type="button" color="danger" outlined icon="heroicon-o-trash" x-show="state" x-on:click="remove()" :disabled="$isDisabled()">
                        Remove
                    </x-filament::button>
                </div>

                <div x-show="live" x-cloak style="display: flex; gap: .5rem; flex-wrap: wrap">
                    <x-filament::button type="button" color="success" icon="heroicon-o-camera" x-on:click="capture()">
                        Capture
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" x-on:click="flip()">
                        Switch camera
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" x-on:click="stop()">
                        Cancel
                    </x-filament::button>
                </div>

                <p x-show="error" x-text="error" x-cloak style="color: rgb(220 38 38); font-size: .85rem; margin: 0"></p>
                <p x-show="state && String(state).startsWith('data:')" x-cloak style="font-size: .8rem; opacity: .65; margin: 0">New photo, saved when you save the form.</p>
            </div>
        </div>

        <input x-ref="fileInput" type="file" accept="image/*" style="display: none" x-on:change="pick($event)">
        <input x-ref="cameraInput" type="file" accept="image/*" capture="user" style="display: none" x-on:change="pick($event)">
    </div>
</x-dynamic-component>
