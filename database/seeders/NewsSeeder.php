<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use App\Models\NewsArticle;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class NewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create categories
        $categories = [
            [
                'name' => 'India',
                'slug' => 'india',
                'description' => 'Latest news from across India',
                'color' => '#FF9500',
                'display_order' => 1,
                'status' => true,
            ],
            [
                'name' => 'World',
                'slug' => 'world',
                'description' => 'International news and updates',
                'color' => '#2563EB',
                'display_order' => 2,
                'status' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Business, finance, and stock market news',
                'color' => '#059669',
                'display_order' => 3,
                'status' => true,
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'description' => 'Cricket, football, tennis and more',
                'color' => '#EA580C',
                'display_order' => 4,
                'status' => true,
            ],
            [
                'name' => 'Entertainment',
                'slug' => 'entertainment',
                'description' => 'Bollywood, movies, and celebrity news',
                'color' => '#EC4899',
                'display_order' => 5,
                'status' => true,
            ],
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Latest tech news and gadgets',
                'color' => '#8B5CF6',
                'display_order' => 6,
                'status' => true,
            ],
            [
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'description' => 'Health, wellness, and lifestyle tips',
                'color' => '#14B8A6',
                'display_order' => 7,
                'status' => true,
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Education news and updates',
                'color' => '#4F46E5',
                'display_order' => 8,
                'status' => true,
            ],
        ];

        foreach ($categories as $category) {
            NewsCategory::firstOrCreate(['slug' => $category['slug']], $category);
        }

        // Get categories for article creation
        $indiaCategory = NewsCategory::where('slug', 'india')->first();
        $worldCategory = NewsCategory::where('slug', 'world')->first();
        $businessCategory = NewsCategory::where('slug', 'business')->first();
        $sportsCategory = NewsCategory::where('slug', 'sports')->first();
        $entertainmentCategory = NewsCategory::where('slug', 'entertainment')->first();
        $techCategory = NewsCategory::where('slug', 'technology')->first();

        // Create sample articles
        $articles = [
            // India articles
            [
                'title' => 'PM Modi Launches New National Development Initiative',
                'slug' => 'pm-modi-launches-national-development-initiative',
                'excerpt' => 'Prime Minister Narendra Modi launched a new comprehensive initiative aimed at accelerating development across all regions of India.',
                'content' => '<p>Prime Minister Narendra Modi today launched a new comprehensive initiative aimed at accelerating development across all regions of India. The initiative focuses on infrastructure development, job creation, and skill enhancement.</p><p>Speaking at the launch event, PM Modi emphasized the importance of inclusive growth and sustainable development. He highlighted various government schemes and their positive impact on the lives of ordinary citizens.</p><p>The new initiative is expected to create lakhs of new employment opportunities and boost the economy in the coming years.</p>',
                'category_ids' => [$indiaCategory->id, $businessCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1576091160671-112d4ae4e2a9?w=800&h=450&fit=crop',
                'author_name' => 'Rajesh Kumar',
                'views' => 15420,
                'shares' => 340,
                'featured' => true,
                'breaking' => false,
                'published_at' => now()->subHours(2),
                'status' => 'published',
            ],
            [
                'title' => 'Budget 2026: Tax Benefits for Middle Class Workers',
                'slug' => 'budget-2026-tax-benefits-middle-class',
                'excerpt' => 'The Union Budget 2026 announced significant tax benefits and exemptions for middle-class workers across the country.',
                'content' => '<p>The Union Budget 2026 has announced significant tax benefits and exemptions for middle-class workers. The Finance Minister highlighted various provisions that will benefit approximately 5 crore salaried employees.</p><p>Key highlights include increased standard deduction, tax-free gratuity benefits, and enhanced investment opportunities in retirement schemes.</p>',
                'category_ids' => [$indiaCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=800&h=450&fit=crop',
                'author_name' => 'Priya Sharma',
                'views' => 8920,
                'shares' => 245,
                'featured' => false,
                'breaking' => false,
                'published_at' => now()->subHours(4),
                'status' => 'published',
            ],
            [
                'title' => 'Delhi Traffic Crisis: New Metro Line Inauguration Next Month',
                'slug' => 'delhi-metro-new-line-inauguration',
                'excerpt' => 'To address the growing traffic congestion in Delhi, a new metro line will be inaugurated next month, easing commute for lakhs of residents.',
                'content' => '<p>To address the growing traffic congestion in Delhi, a new metro line will be inaugurated next month. The new line is expected to ease commute for lakhs of residents and reduce vehicular traffic significantly.</p>',
                'category_ids' => [$indiaCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1553531088-f352eff62ccf?w=800&h=450&fit=crop',
                'author_name' => 'Amit Verma',
                'views' => 6234,
                'shares' => 178,
                'featured' => false,
                'breaking' => true,
                'published_at' => now()->subHours(1),
                'status' => 'published',
            ],
            // World articles
            [
                'title' => 'Global Climate Summit Reaches Historic Agreement',
                'slug' => 'global-climate-summit-historic-agreement',
                'excerpt' => 'World leaders gathered at the Global Climate Summit reached a landmark agreement to reduce carbon emissions by 50% by 2030.',
                'content' => '<p>World leaders gathered at the Global Climate Summit have reached a landmark agreement to reduce carbon emissions by 50% by 2030. This historic accord represents the most ambitious climate action commitment to date.</p><p>The agreement includes provisions for renewable energy investments, forest protection, and technology transfer to developing nations.</p>',
                'category_ids' => [$worldCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1569163139394-de4798aa62b6?w=800&h=450&fit=crop',
                'author_name' => 'Michael Chen',
                'views' => 12340,
                'shares' => 456,
                'featured' => true,
                'breaking' => false,
                'published_at' => now()->subHours(3),
                'status' => 'published',
            ],
            [
                'title' => 'European Stocks Rally on Positive Economic Data',
                'slug' => 'european-stocks-rally-economic-data',
                'excerpt' => 'European stock markets showed strong gains today following better-than-expected economic indicators from major EU economies.',
                'content' => '<p>European stock markets showed strong gains today following better-than-expected economic indicators from major EU economies. The positive data has boosted investor sentiment across the continent.</p>',
                'category_ids' => [$worldCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=450&fit=crop',
                'author_name' => 'Sarah Johnson',
                'views' => 4567,
                'shares' => 123,
                'featured' => false,
                'breaking' => false,
                'published_at' => now()->subHours(5),
                'status' => 'published',
            ],
            // Business articles
            [
                'title' => 'Tech Giant Announces Record Quarterly Profits',
                'slug' => 'tech-giant-record-quarterly-profits',
                'excerpt' => 'A leading technology company announced record-breaking quarterly profits, exceeding analyst expectations by 25%.',
                'content' => '<p>A leading technology company announced record-breaking quarterly profits of $50 billion, exceeding analyst expectations by 25%. The company attributed the strong performance to increased demand for cloud services and AI solutions.</p>',
                'category_ids' => [$businessCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=800&h=450&fit=crop',
                'author_name' => 'David Kumar',
                'views' => 9876,
                'shares' => 234,
                'featured' => true,
                'breaking' => false,
                'published_at' => now()->subHours(2),
                'status' => 'published',
            ],
            [
                'title' => 'Rupee Strengthens Against Dollar on Positive Sentiment',
                'slug' => 'rupee-strengthens-against-dollar',
                'excerpt' => 'The Indian rupee appreciated against the US dollar, buoyed by strong inflow of foreign investments and positive macroeconomic data.',
                'content' => '<p>The Indian rupee appreciated against the US dollar on Tuesday, buoyed by strong inflow of foreign investments and positive macroeconomic data. The rupee gained 45 paise against the dollar.</p>',
                'category_ids' => [$businessCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1559163253-f4fcb76e6c5f?w=800&h=450&fit=crop',
                'author_name' => 'Rajiv Singh',
                'views' => 5432,
                'shares' => 98,
                'featured' => false,
                'breaking' => false,
                'published_at' => now()->subHours(6),
                'status' => 'published',
            ],
            // Sports articles
            [
                'title' => 'India Wins T20 Series Against West Indies',
                'slug' => 'india-wins-t20-series-west-indies',
                'excerpt' => 'India clinched the T20 series against West Indies with a thrilling 2-1 victory in the final match at Mumbai.',
                'content' => '<p>India clinched the T20 series against West Indies with a thrilling 2-1 victory in the final match at Mumbai. The Indian team displayed exceptional batting and bowling performances throughout the series.</p><p>Virat Kohli scored a brilliant century in the final match, which proved to be the match-winning performance for India.</p>',
                'category_ids' => [$sportsCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800&h=450&fit=crop',
                'author_name' => 'Arjun Reddy',
                'views' => 23456,
                'shares' => 567,
                'featured' => true,
                'breaking' => true,
                'published_at' => now()->subHours(1),
                'status' => 'published',
            ],
            [
                'title' => 'Premier League: Liverpool Tops Table After Win',
                'slug' => 'premier-league-liverpool-tops-table',
                'excerpt' => 'Liverpool strengthened their position at the top of the Premier League with a convincing 3-1 victory over Manchester United.',
                'content' => '<p>Liverpool strengthened their position at the top of the Premier League with a convincing 3-1 victory over Manchester United. Mohamed Salah scored twice in the match.</p>',
                'category_ids' => [$sportsCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1519167758481-dc80c0679abc?w=800&h=450&fit=crop',
                'author_name' => 'James Wilson',
                'views' => 7890,
                'shares' => 145,
                'featured' => false,
                'breaking' => false,
                'published_at' => now()->subHours(7),
                'status' => 'published',
            ],
            // Entertainment articles
            [
                'title' => 'Bollywood Blockbuster Breaks Box Office Records',
                'slug' => 'bollywood-blockbuster-breaks-box-office-records',
                'excerpt' => 'A highly-anticipated Bollywood film has broken multiple box office records in its opening weekend, earning over 100 crores.',
                'content' => '<p>A highly-anticipated Bollywood film has broken multiple box office records in its opening weekend, earning over 100 crores. The film has been receiving rave reviews from critics and audiences alike.</p>',
                'category_ids' => [$entertainmentCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=800&h=450&fit=crop',
                'author_name' => 'Neha Kapoor',
                'views' => 18234,
                'shares' => 412,
                'featured' => true,
                'breaking' => false,
                'published_at' => now()->subHours(3),
                'status' => 'published',
            ],
            [
                'title' => 'Celebrity Couple Announces Engagement',
                'slug' => 'celebrity-couple-announces-engagement',
                'excerpt' => 'A popular Bollywood couple announced their engagement on social media, much to the delight of their fans.',
                'content' => '<p>A popular Bollywood couple announced their engagement on social media, much to the delight of their fans. The couple shared loved-up pictures from a romantic getaway in Paris.</p>',
                'category_ids' => [$entertainmentCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1494799802753-7d5d14e3563e?w=800&h=450&fit=crop',
                'author_name' => 'Shreya Verma',
                'views' => 12456,
                'shares' => 234,
                'featured' => false,
                'breaking' => false,
                'published_at' => now()->subHours(8),
                'status' => 'published',
            ],
            // Tech articles
            [
                'title' => 'AI Technology Revolutionizes Healthcare Industry',
                'slug' => 'ai-technology-revolutionizes-healthcare',
                'excerpt' => 'Artificial Intelligence is transforming the healthcare industry with early disease detection and personalized treatment plans.',
                'content' => '<p>Artificial Intelligence is transforming the healthcare industry with early disease detection and personalized treatment plans. Hospitals across the world are adopting AI-powered diagnostic systems to improve patient outcomes.</p>',
                'category_ids' => [$techCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=800&h=450&fit=crop',
                'author_name' => 'Akshay Patel',
                'views' => 11234,
                'shares' => 298,
                'featured' => true,
                'breaking' => false,
                'published_at' => now()->subHours(4),
                'status' => 'published',
            ],
            [
                'title' => 'Quantum Computing Breakthrough Announced',
                'slug' => 'quantum-computing-breakthrough',
                'excerpt' => 'Scientists announce a major breakthrough in quantum computing, achieving a new record in quantum error correction.',
                'content' => '<p>Scientists have announced a major breakthrough in quantum computing, achieving a new record in quantum error correction. This development brings practical quantum computers closer to reality.</p>',
                'category_ids' => [$techCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1535905557558-afc4877a26fc?w=800&h=450&fit=crop',
                'author_name' => 'Dr. Rohan Singh',
                'views' => 8765,
                'shares' => 187,
                'featured' => false,
                'breaking' => false,
                'published_at' => now()->subHours(9),
                'status' => 'published',
            ],
            [
                'title' => 'New Smartphone Released with Advanced Features',
                'slug' => 'new-smartphone-advanced-features',
                'excerpt' => 'A leading smartphone manufacturer released its latest flagship model with groundbreaking camera technology and processor.',
                'content' => '<p>A leading smartphone manufacturer released its latest flagship model featuring a revolutionary camera system and ultra-fast processor. The device also features an improved battery life of up to 3 days.</p>',
                'category_ids' => [$techCategory->id],
                'featured_image' => 'https://images.unsplash.com/photo-1511707267537-b85faf00021e?w=800&h=450&fit=crop',
                'author_name' => 'Vikram Desai',
                'views' => 9542,
                'shares' => 215,
                'featured' => false,
                'breaking' => false,
                'published_at' => now()->subHours(10),
                'status' => 'published',
            ],
        ];

        foreach ($articles as $articleData) {
            $categoryIds = $articleData['category_ids'] ?? [];
            unset($articleData['category_ids']);

            $article = NewsArticle::firstOrCreate(
                ['slug' => $articleData['slug']],
                $articleData
            );

            $article->categories()->sync($categoryIds);
        }
    }
}
