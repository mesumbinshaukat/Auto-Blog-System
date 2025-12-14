<div 
    x-data="{ 
        show: !localStorage.getItem('cookie_consent'), 
        accept() { 
            localStorage.setItem('cookie_consent', 'accepted'); 
            this.show = false; 
        },
        reject() {
            localStorage.setItem('cookie_consent', 'rejected');
            this.show = false;
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-full opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    class="fixed bottom-0 left-0 right-0 z-50 p-4 bg-white border-t shadow-2xl md:p-6"
    style="display: none;" 
    x-init="$el.style.display = 'block'"
>
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
        <!-- Message -->
        <div class="space-y-2 text-sm text-gray-600 flex-1">
            <h3 class="font-bold text-gray-900 text-lg">🍪 We Value Your Privacy & Our Content</h3>
            <p>
                We use cookies to enable the advertisements that keep this website free. 
                We <strong>do NOT sell your personal data</strong> to anyone.
                By continuing, you accept our use of cookies for ads and analytics.
            </p>
            <p class="text-xs">
                Even if you reject tracking, you will still see advertisements (just less relevant ones). 
                See our <a href="{{ route('privacy-policy') }}" class="underline hover:text-blue-600">Privacy Policy</a>.
            </p>
        </div>

        <!-- Buttons -->
        <div class="flex items-center gap-3 shrink-0">
            <button @click="reject()" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Reject Non-Essential
            </button>
            <button @click="accept()" class="px-6 py-2 text-sm font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-md transition transform hover:scale-105">
                Accept All
            </button>
        </div>
    </div>
</div>
