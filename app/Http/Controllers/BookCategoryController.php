<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\BookContent;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookCategoryController extends Controller
{
    public function index($book = null)
    {
        // If book is a string/int (ID), fetch the model
        if ($book && ! ($book instanceof Book)) {
            $book = Book::find($book);
        }

        $categories = Category::whereNull('parent_id')
            ->where('type', 'book')
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        // Load books for each category
        foreach ($categories as $cat) {
            $cat->books = Book::where('book_category_id', $cat->id)
                ->orderBy('seq')
                ->get();
        }

        $chapters = [];
        $selectedBook = null;

        if ($book) {
            $book->load('category');
            $selectedBook = $book;

            $chapters = BookChapter::where('book_id', $book->id)
                ->whereNull('parent_id')
                ->with(['children' => function ($query) {
                    $query->with(['children' => function ($q) {
                        $q->orderBy('seq');
                    }])->orderBy('seq');
                }])
                ->orderBy('seq')
                ->get();
        }

        return Inertia::render('Book/Index', [
            'categories' => $categories,
            'storeUrl' => '/book/category',
            'chapters' => $chapters,
            'selectedBook' => $selectedBook,
        ]);
    }

    public function showBook(Book $book)
    {
        $book->load('category');

        $chapters = BookChapter::where('book_id', $book->id)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->orderBy('seq');
            }])
            ->orderBy('seq')
            ->get();

        return Inertia::render('Book/Detail', [
            'book' => $book,
            'chapters' => $chapters,
        ]);
    }

    public function showContent(BookChapter $chapter)
    {
        $chapter->load(['book', 'parent']);

        $contents = BookContent::where('book_chapters_id', $chapter->id)
            ->with('audios')
            ->orderBy('page')
            ->get();

        return Inertia::render('Book/Content', [
            'chapter' => $chapter,
            'contents' => $contents,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:category,id',
            'seq' => 'nullable|integer',
        ]);

        $position = $request->seq;

        $categories = Category::where('parent_id', $request->parent_id)
            ->where('type', 'book')
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        $totalCategories = $categories->count();

        foreach ($categories as $index => $cat) {
            $cat->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $totalCategories) {
            $newSeq = $totalCategories + 1;
        } else {
            Category::where('parent_id', $request->parent_id)
                ->where('type', 'book')
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Category::create([
            'title' => $request->title,
            'parent_id' => $request->parent_id,
            'seq' => $newSeq,
            'type' => 'book',
        ]);

        return back();
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $validated = $request->only(['title', 'seq']);

        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq'];

            DB::transaction(function () use ($category, $targetPosition) {
                $allItems = Category::where('parent_id', $category->parent_id)
                    ->where('type', 'book')
                    ->orderBy('seq')
                    ->lockForUpdate()
                    ->get();

                $currentIndex = $allItems->search(fn ($c) => $c->id === $category->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Category::where('parent_id', $category->parent_id)
                        ->where('type', 'book')
                        ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                        ->decrement('seq');

                    $movingItem->seq = $targetSeq;
                } else {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Category::where('parent_id', $category->parent_id)
                        ->where('type', 'book')
                        ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                        ->increment('seq');

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $category->fill($validated);
        $category->save();

        return back();
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back();
    }

    // Book CRUD
    public function storeBook(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|image|mimes:jpeg,jpg,png,gif|max:2048',
            'url_pdf' => 'nullable|mimes:pdf|max:10240',
            'seq' => 'nullable|integer',
        ], [
            'title.required' => 'Judul wajib diisi.',
            'title.max' => 'Judul maksimal 255 karakter.',
            'url.required' => 'Foto Cover wajib diisi.',
            'url.image' => 'Foto Cover harus berupa gambar.',
            'url.mimes' => 'Foto Cover harus berformat jpeg, jpg, png, atau gif.',
            'url.max' => 'Foto Cover maksimal 2MB.',
            'url_pdf.mimes' => 'File PDF harus berformat pdf.',
            'url_pdf.max' => 'File PDF maksimal 10MB.',
        ]);

        $uploadPath = public_path('assets/upload/book');
        if (! file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $coverUrl = null;
        if ($request->hasFile('url')) {
            $file = $request->file('url');
            $filename = md5(uniqid().'_'.$file->getClientOriginalName()).'.'.$file->getClientOriginalExtension();
            $file->move($uploadPath, $filename);
            $coverUrl = config('app.url').'/assets/upload/book/'.$filename;
        }

        $pdfUrl = null;
        if ($request->hasFile('url_pdf')) {
            $file = $request->file('url_pdf');
            $filename = md5(uniqid().'_'.$file->getClientOriginalName()).'.'.$file->getClientOriginalExtension();
            $file->move($uploadPath, $filename);
            $pdfUrl = config('app.url').'/assets/upload/book/'.$filename;
        }

        $position = $request->seq;

        $books = Book::where('book_category_id', $category->id)
            ->orderBy('seq')
            ->get();

        $total = $books->count();

        foreach ($books as $index => $book) {
            $book->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            Book::where('book_category_id', $category->id)
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Book::create([
            'title' => $request->title,
            'url' => $coverUrl,
            'url_pdf' => $pdfUrl,
            'book_category_id' => $category->id,
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function updateBook(Request $request, Book $book)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
            'url_pdf' => 'nullable|mimes:pdf|max:10240',
            'seq' => 'nullable|integer',
        ], [
            'title.required' => 'Judul wajib diisi.',
            'title.max' => 'Judul maksimal 255 karakter.',
            'url.image' => 'Foto Cover harus berupa gambar.',
            'url.mimes' => 'Foto Cover harus berformat jpeg, jpg, png, atau gif.',
            'url.max' => 'Foto Cover maksimal 2MB.',
            'url_pdf.mimes' => 'File PDF harus berformat pdf.',
            'url_pdf.max' => 'File PDF maksimal 10MB.',
        ]);

        $uploadPath = public_path('assets/upload/book');
        if (! file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $data = [
            'title' => $request->title,
        ];

        if ($request->hasFile('url')) {
            // Delete old file - handle both relative and full URLs
            if ($book->url) {
                $oldPath = str_starts_with($book->url, 'http')
                    ? str_replace(config('app.url'), '', $book->url)
                    : $book->url;
                if (file_exists(public_path($oldPath))) {
                    unlink(public_path($oldPath));
                }
            }

            $file = $request->file('url');
            $filename = md5(uniqid().'_'.$file->getClientOriginalName()).'.'.$file->getClientOriginalExtension();
            $file->move($uploadPath, $filename);
            $data['url'] = config('app.url').'/assets/upload/book/'.$filename;
        }

        if ($request->hasFile('url_pdf')) {
            // Delete old file - handle both relative and full URLs
            if ($book->url_pdf) {
                $oldPath = str_starts_with($book->url_pdf, 'http')
                    ? str_replace(config('app.url'), '', $book->url_pdf)
                    : $book->url_pdf;
                if (file_exists(public_path($oldPath))) {
                    unlink(public_path($oldPath));
                }
            }

            $file = $request->file('url_pdf');
            $filename = md5(uniqid().'_'.$file->getClientOriginalName()).'.'.$file->getClientOriginalExtension();
            $file->move($uploadPath, $filename);
            $data['url_pdf'] = config('app.url').'/assets/upload/book/'.$filename;
        }

        if (isset($request->seq)) {
            $targetPosition = $request->seq;

            DB::transaction(function () use ($book, $targetPosition) {
                $allItems = Book::where('book_category_id', $book->book_category_id)
                    ->orderBy('seq')
                    ->lockForUpdate()
                    ->get();

                $currentIndex = $allItems->search(fn ($b) => $b->id === $book->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Book::where('book_category_id', $book->book_category_id)
                        ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                        ->decrement('seq');

                    $movingItem->seq = $targetSeq;
                } else {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Book::where('book_category_id', $book->book_category_id)
                        ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                        ->increment('seq');

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });
        }

        $book->update($data);

        return back();
    }

    public function destroyBook(Book $book)
    {
        // Delete cover file - handle both relative and full URLs
        if ($book->url) {
            $coverPath = str_starts_with($book->url, 'http')
                ? str_replace(config('app.url'), '', $book->url)
                : $book->url;
            if (file_exists(public_path($coverPath))) {
                unlink(public_path($coverPath));
            }
        }

        // Delete PDF file - handle both relative and full URLs
        if ($book->url_pdf) {
            $pdfPath = str_starts_with($book->url_pdf, 'http')
                ? str_replace(config('app.url'), '', $book->url_pdf)
                : $book->url_pdf;
            if (file_exists(public_path($pdfPath))) {
                unlink(public_path($pdfPath));
            }
        }

        $book->delete();

        return back();
    }

    // Chapter CRUD
    public function storeChapter(Request $request, Book $book)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
            'parent_id' => 'nullable|exists:book_chapters,id',
            'have_child' => 'required|integer',
        ]);

        $position = $request->seq;
        $parentId = $request->parent_id;

        if ($parentId) {
            $chapters = BookChapter::where('parent_id', $parentId)
                ->orderBy('seq')
                ->get();
        } else {
            $chapters = BookChapter::where('book_id', $book->id)
                ->whereNull('parent_id')
                ->orderBy('seq')
                ->get();
        }

        $total = $chapters->count();

        foreach ($chapters as $index => $chapter) {
            $chapter->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            if ($parentId) {
                BookChapter::where('parent_id', $parentId)
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            } else {
                BookChapter::where('book_id', $book->id)
                    ->whereNull('parent_id')
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            }

            $newSeq = $position;
        }

        BookChapter::create([
            'title' => $request->title,
            'book_id' => $book->id,
            'parent_id' => $parentId,
            'seq' => $newSeq,
            'have_child' => $request->have_child,
        ]);

        return back();
    }

    public function updateChapter(Request $request, BookChapter $chapter)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $validated = $request->only(['title', 'seq']);

        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq'];

            DB::transaction(function () use ($chapter, $targetPosition) {
                if ($chapter->parent_id) {
                    $allItems = BookChapter::where('parent_id', $chapter->parent_id)
                        ->orderBy('seq')
                        ->lockForUpdate()
                        ->get();
                } else {
                    $allItems = BookChapter::where('book_id', $chapter->book_id)
                        ->whereNull('parent_id')
                        ->orderBy('seq')
                        ->lockForUpdate()
                        ->get();
                }

                $currentIndex = $allItems->search(fn ($c) => $c->id === $chapter->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    if ($chapter->parent_id) {
                        BookChapter::where('parent_id', $chapter->parent_id)
                            ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                            ->decrement('seq');
                    } else {
                        BookChapter::where('book_id', $chapter->book_id)
                            ->whereNull('parent_id')
                            ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                            ->decrement('seq');
                    }

                    $movingItem->seq = $targetSeq;
                } else {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    if ($chapter->parent_id) {
                        BookChapter::where('parent_id', $chapter->parent_id)
                            ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                            ->increment('seq');
                    } else {
                        BookChapter::where('book_id', $chapter->book_id)
                            ->whereNull('parent_id')
                            ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                            ->increment('seq');
                    }

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $chapter->fill($validated);
        $chapter->save();

        return back();
    }

    public function destroyChapter(BookChapter $chapter)
    {
        $chapter->delete();

        return back();
    }

    // Content CRUD
    public function storeContent(Request $request, BookChapter $chapter)
    {
        $request->validate([
            'page' => 'required|integer',
            'content' => 'required|string',
        ]);

        // Check if page number already exists
        $existingContent = BookContent::where('book_chapters_id', $chapter->id)
            ->where('page', $request->page)
            ->first();

        if ($existingContent) {
            // Find the highest page number
            $maxPage = BookContent::where('book_chapters_id', $chapter->id)
                ->max('page') ?? 0;

            // Move existing content to next available page
            $existingContent->update(['page' => $maxPage + 1]);
        }

        BookContent::create([
            'book_chapters_id' => $chapter->id,
            'page' => $request->page,
            'content' => $request->content,
        ]);

        return back();
    }

    public function updateContent(Request $request, BookContent $content)
    {
        $request->validate([
            'page' => 'required|integer',
            'content' => 'required|string',
        ]);

        // Check if the new page number is different and already exists
        if ($request->page !== $content->page) {
            $existingContent = BookContent::where('book_chapters_id', $content->book_chapters_id)
                ->where('page', $request->page)
                ->where('id', '!=', $content->id)
                ->first();

            if ($existingContent) {
                // Swap page numbers
                $oldPage = $content->page;
                $newPage = $request->page;

                // Temporarily set to a high number to avoid unique constraint issues
                $tempPage = BookContent::where('book_chapters_id', $content->book_chapters_id)
                    ->max('page') + 1000;

                $content->update(['page' => $tempPage]);
                $existingContent->update(['page' => $oldPage]);
                $content->update(['page' => $newPage]);
            } else {
                $content->update(['page' => $request->page]);
            }
        }

        $content->update(['content' => $request->content]);

        return back();
    }

    public function destroyContent(BookContent $content)
    {
        $content->delete();

        return back();
    }
}
