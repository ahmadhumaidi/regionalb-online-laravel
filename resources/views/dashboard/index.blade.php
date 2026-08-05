<x-layouts.app title="Dashboard Utama" active="dashboard">
    <x-dashboard.filter-bar :filters="$filters" :reference-options="$referenceOptions" />
    <x-dashboard.summary-cards :cards="$summaryCards" />
    <x-dashboard.registration-recap :recap="$registrationRecap" :budget="$budget" />
    <x-dashboard.achievement-grid
        :campus-closing="$campusClosing"
        :top-staff-achievement="$topStaffAchievement"
        :top-staff-max-value="$topStaffMaxValue"
    />
    <x-dashboard.gamification-panel :gamification="$gamification" />
    <x-dashboard.ranking-table :ranking="$ranking" />
    <x-dashboard.daily-report-table :reports="$dailyReports" />
</x-layouts.app>
