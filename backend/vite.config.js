import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
            fonts: [
                bunny("Instrument Sans", {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        allowedHosts: true, // Ini sudah benar
        host: "0.0.0.0", // Tambahkan ini agar listen di semua network interface
        hmr: {
            host: "unlamentable-freeman-supermedially.ngrok-free.dev", // Ganti dengan domain ngrok kamu
        },
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
