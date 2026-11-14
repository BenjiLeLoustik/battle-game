export default function LeaderboardFilter(element) {
    element.querySelector('.leaderboard__select').addEventListener('change', () => element.submit());
}