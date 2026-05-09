import { ref } from 'vue';
import axios from 'axios';

const activeStream = ref(null);
const isStreaming = ref(false);
const isLoading = ref(false);
const error = ref(null);
const liveRooms = ref([]);

export function useLiveStream() {
    async function getToken(roomName) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await axios.post('/stream/token', { room: roomName });
            return data;
        } catch (e) {
            error.value = e.response?.data?.error || 'Nepodařilo se získat token.';
            return null;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchRooms() {
        try {
            const { data } = await axios.get('/stream/rooms');
            liveRooms.value = data.rooms || [];
        } catch {
            liveRooms.value = [];
        }
    }

    async function startStream(roomName) {
        const tokenData = await getToken(roomName);
        if (!tokenData) return null;

        activeStream.value = tokenData;
        isStreaming.value = true;
        return tokenData;
    }

    function stopStream() {
        activeStream.value = null;
        isStreaming.value = false;
    }

    return {
        activeStream,
        isStreaming,
        isLoading,
        error,
        liveRooms,
        getToken,
        fetchRooms,
        startStream,
        stopStream,
    };
}
