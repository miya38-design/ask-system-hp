/* ============================================
   Firebase Configuration (Dummy)
   本番時は実際の設定値に差し替えてください
   ============================================ */

const firebaseConfig = {
  apiKey: "AIzaSyB0FakUZUkwpeNiOnaZ9IRxJIF44L1lZBY",
  authDomain: "akos-hp.firebaseapp.com",
  projectId: "akos-hp",
  storageBucket: "akos-hp.firebasestorage.app",
  messagingSenderId: "96169699380",
  appId: "1:96169699380:web:5d7f837c33c333e1e48261",
  measurementId: "G-BR3Q64JGLQ"
};

// Firebase is loaded via CDN in HTML
// These will be initialized after Firebase SDK loads
let firebaseApp = null;
let auth = null;
let db = null;

function initFirebase() {
  try {
    if (typeof firebase !== 'undefined') {
      firebaseApp = firebase.initializeApp(firebaseConfig);
      auth = firebase.auth();
      db = firebase.firestore();
      console.log('Firebase initialized successfully');
      return true;
    }
  } catch (e) {
    console.warn('Firebase SDK not loaded. Running in demo mode.', e);
  }
  return false;
}

// Demo mode flag (when Firebase is not configured)
let isDemoMode = true;

// Demo user data
const demoUsers = {
  parent: {
    uid: 'demo-parent-001',
    email: 'parent@demo.ada.jp',
    displayName: '山田 太郎',
    role: 'parent',
    children: ['demo-student-001']
  },
  student: {
    uid: 'demo-student-001',
    email: 'student@demo.ada.jp',
    displayName: '山田 花子',
    role: 'student',
    parentId: 'demo-parent-001',
    level: 12,
    exp: 2450,
    expToNext: 3000
  },
  instructor: {
    uid: 'demo-instructor-001',
    email: 'instructor@demo.ada.jp',
    displayName: '田中 一郎',
    role: 'instructor',
    subjects: ['算数', '国語', 'IT・プログラミング'],
    area: '八代市'
  }
};

// Demo progress data
const demoProgress = {
  math: 78,
  japanese: 85,
  english: 62,
  science: 71,
  social: 68,
  it: 92
};

// Demo schedule data (current month)
function generateDemoSchedules() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const schedules = [];

  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(year, month, d);
    const dow = date.getDay();
    // Only weekdays
    if (dow === 0 || dow === 6) continue;

    const rand = Math.random();
    let status;
    if (rand < 0.5) status = 'available';
    else if (rand < 0.8) status = 'limited';
    else status = 'full';

    schedules.push({
      date: d,
      dayOfWeek: dow,
      status: status,
      capacity: 10,
      currentBookings: status === 'full' ? 10 : status === 'limited' ? 7 + Math.floor(Math.random() * 3) : Math.floor(Math.random() * 5)
    });
  }
  return schedules;
}

// Demo messages
const demoMessages = [
  {
    sender: '田中先生',
    date: '2026/03/25',
    body: '花子さん、今日の算数のテストではとても良い結果でした！特に分数の計算が正確になっています。この調子で頑張りましょう。'
  },
  {
    sender: '佐藤先生',
    date: '2026/03/22',
    body: 'プログラミングの授業で、Scratchを使ったゲーム制作に挑戦しました。アイデアが豊富で、独創的な作品が出来上がりそうです。'
  },
  {
    sender: '田中先生',
    date: '2026/03/18',
    body: '国語の読解力が着実に向上しています。漢字テストでも90点を取れました。ご自宅での学習も効果が出ているようです。'
  }
];

// Demo missions
const demoMissions = [
  { id: 1, title: '算数ドリル 5ページ完了', expReward: 50, completed: false },
  { id: 2, title: '英単語テスト 30問チャレンジ', expReward: 80, completed: false },
  { id: 3, title: 'タイピング練習 10分間', expReward: 30, completed: true },
  { id: 4, title: '読書感想文 1つ提出', expReward: 100, completed: false },
  { id: 5, title: 'Scratch ミニゲーム作成', expReward: 120, completed: false }
];

// Demo attendance (stamps)
const demoAttendance = {
  stampCount: 7,
  totalRequired: 10,
  giftEarned: false
};

// Demo students list for instructor & admin
const demoStudentsList = [
  { uid: 'demo-student-001', name: '山田 花子', parentName: '山田 太郎', parentEmail: 'parent@demo.ada.jp', area: '八代市', course: 'premium', level: 12, exp: 2450, attendance: 85, joinDate: '2025/09/01' },
  { uid: 'demo-student-002', name: '佐藤 健太', parentName: '佐藤 美咲', parentEmail: 'sato@demo.ada.jp', area: '八代市', course: 'standard-a', level: 8, exp: 1200, attendance: 92, joinDate: '2025/10/15' },
  { uid: 'demo-student-003', name: '鈴木 あかり', parentName: '鈴木 大輔', parentEmail: 'suzuki@demo.ada.jp', area: '水俣市', course: 'standard-b', level: 15, exp: 3800, attendance: 78, joinDate: '2025/04/01' },
  { uid: 'demo-student-004', name: '高橋 翔', parentName: '高橋 裕子', parentEmail: 'takahashi@demo.ada.jp', area: '芦北町', course: 'premium', level: 6, exp: 800, attendance: 95, joinDate: '2026/01/10' },
  { uid: 'demo-student-005', name: '田中 優斗', parentName: '田中 幸子', parentEmail: 'tanaka@demo.ada.jp', area: '津奈木町', course: 'standard-a', level: 10, exp: 2100, attendance: 88, joinDate: '2025/06/01' },
  { uid: 'demo-student-006', name: '中村 さくら', parentName: '中村 健一', parentEmail: 'nakamura@demo.ada.jp', area: '出水市', course: 'standard-b', level: 9, exp: 1650, attendance: 82, joinDate: '2025/11/01' },
  { uid: 'demo-student-007', name: '小林 陸', parentName: '小林 真由美', parentEmail: 'kobayashi@demo.ada.jp', area: '八代市', course: 'premium', level: 18, exp: 5200, attendance: 97, joinDate: '2025/01/15' },
  { uid: 'demo-student-008', name: '加藤 凛', parentName: '加藤 誠', parentEmail: 'kato@demo.ada.jp', area: '水俣市', course: 'standard-a', level: 7, exp: 980, attendance: 73, joinDate: '2026/02/01' },
  { uid: 'demo-student-009', name: '渡辺 蒼', parentName: '渡辺 由美', parentEmail: 'watanabe@demo.ada.jp', area: '芦北町', course: 'standard-a', level: 11, exp: 2300, attendance: 90, joinDate: '2025/07/20' },
  { uid: 'demo-student-010', name: '伊藤 結月', parentName: '伊藤 大輝', parentEmail: 'ito@demo.ada.jp', area: '出水市', course: 'premium', level: 14, exp: 3500, attendance: 86, joinDate: '2025/05/10' },
  { uid: 'demo-student-011', name: '松本 大翔', parentName: '松本 恵子', parentEmail: 'matsumoto@demo.ada.jp', area: '八代市', course: 'standard-b', level: 5, exp: 600, attendance: 91, joinDate: '2026/03/01' },
  { uid: 'demo-student-012', name: '井上 咲希', parentName: '井上 浩二', parentEmail: 'inoue@demo.ada.jp', area: '津奈木町', course: 'standard-a', level: 13, exp: 3100, attendance: 84, joinDate: '2025/03/15' }
];

// Admin demo user
demoUsers.admin = {
  uid: 'demo-admin-001',
  email: 'admin@demo.ada.jp',
  displayName: '管理者',
  role: 'admin'
};

// Admin KPI statistics
const demoAdminStats = {
  totalStudents: 12,
  activeStudents: 11,
  averageAttendance: 87,
  monthlyRevenue: 128000,
  newStudentsThisMonth: 2,
  totalAreas: 5
};

// Revenue data (monthly)
const demoRevenueData = {
  labels: ['10月', '11月', '12月', '1月', '2月', '3月', '4月'],
  revenue: [85000, 96000, 102000, 108000, 115000, 122000, 128000],
  byArea: {
    '八代市': 48000,
    '水俣市': 27000,
    '芦北町': 21000,
    '津奈木町': 15000,
    '出水市': 17000
  },
  byCourse: {
    'プレミアム': 48000,
    'スタンダードA': 45000,
    'スタンダードB': 18000,
    'シニア': 17000
  }
};

// All demo missions (admin view)
const demoAllMissions = [
  { id: 1, title: '算数ドリル 5ページ完了', expReward: 50, assignedTo: '全員', status: 'active', createdAt: '2026/04/28' },
  { id: 2, title: '英単語テスト 30問チャレンジ', expReward: 80, assignedTo: '全員', status: 'active', createdAt: '2026/04/27' },
  { id: 3, title: 'タイピング練習 10分間', expReward: 30, assignedTo: '全員', status: 'active', createdAt: '2026/04/25' },
  { id: 4, title: '読書感想文 1つ提出', expReward: 100, assignedTo: 'LV10以上', status: 'active', createdAt: '2026/04/24' },
  { id: 5, title: 'Scratch ミニゲーム作成', expReward: 120, assignedTo: '全員', status: 'active', createdAt: '2026/04/22' },
  { id: 6, title: '計算チャレンジ 100問', expReward: 150, assignedTo: 'LV5以上', status: 'draft', createdAt: '2026/04/30' },
  { id: 7, title: '都道府県クイズ', expReward: 60, assignedTo: '全員', status: 'completed', createdAt: '2026/04/15' }
];

// Demo sent messages (instructor)
const demoSentMessages = [
  {
    id: 1,
    to: '山田 太郎（花子さんの保護者）',
    date: '2026/04/28',
    body: '花子さんの算数の成績が向上しています。引き続き応援をお願いします。'
  },
  {
    id: 2,
    to: '佐藤 美咲（健太さんの保護者）',
    date: '2026/04/25',
    body: 'プログラミング授業で積極的に質問してくれています。自宅でも練習してみてください。'
  }
];

window.FirebaseConfig = {
  initFirebase,
  isDemoMode,
  demoUsers,
  demoProgress,
  generateDemoSchedules,
  demoMessages,
  demoMissions,
  demoAttendance,
  demoStudentsList,
  demoSentMessages,
  demoAdminStats,
  demoRevenueData,
  demoAllMissions
};
