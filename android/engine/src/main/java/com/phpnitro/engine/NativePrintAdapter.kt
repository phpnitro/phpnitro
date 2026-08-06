package com.phpnitro.engine

import android.content.Context
import android.os.Bundle
import android.os.CancellationSignal
import android.os.ParcelFileDescriptor
import android.print.PageRange
import android.print.PrintAttributes
import android.print.PrintDocumentAdapter
import android.print.PrintDocumentInfo
import android.print.pdf.PrintedPdfDocument
import java.io.FileOutputStream
import kotlin.math.ceil
import kotlin.math.max

/**
 * Native replacement for WebAppInterface.printPage()'s
 * WebView.createPrintDocumentAdapter() — same PrintManager/system print
 * dialog, but the PDF pages are built by replaying this screen's own draw
 * commands (NativeCanvasView.drawForPrint()) onto PrintedPdfDocument's
 * page Canvas, so there's no WebView anywhere in the pipeline. Content is
 * paginated top-to-bottom by contentHeight — a document has no scroll
 * position, so "fixed" commands (AppBar/BottomNav) are just painted once
 * at their authored dp coordinates like everything else, not pinned.
 */
class NativePrintAdapter(
    private val context: Context,
    private val canvasView: NativeCanvasView,
    private val jobName: String,
) : PrintDocumentAdapter() {

    private var printAttributes: PrintAttributes? = null

    override fun onLayout(
        oldAttributes: PrintAttributes?,
        newAttributes: PrintAttributes,
        cancellationSignal: CancellationSignal,
        callback: LayoutResultCallback,
        extras: Bundle?,
    ) {
        printAttributes = newAttributes
        if (cancellationSignal.isCanceled) {
            callback.onLayoutCancelled()
            return
        }
        val info = PrintDocumentInfo.Builder(jobName)
            .setContentType(PrintDocumentInfo.CONTENT_TYPE_DOCUMENT)
            .build()
        callback.onLayoutFinished(info, oldAttributes != newAttributes)
    }

    override fun onWrite(
        pages: Array<out PageRange>,
        destination: ParcelFileDescriptor,
        cancellationSignal: CancellationSignal,
        callback: WriteResultCallback,
    ) {
        val attributes = printAttributes ?: run {
            callback.onWriteFailed("No print attributes")
            return
        }
        val (commands, contentHeightDp) = canvasView.printSnapshot()
        val screenWidthDp = canvasView.lastScreenWidthDp
        val pdfDocument = PrintedPdfDocument(context, attributes)
        try {
            val scale = pdfDocument.pageWidth / screenWidthDp
            val pageHeightDp = pdfDocument.pageHeight / scale
            val totalPages = max(1, ceil((contentHeightDp / pageHeightDp).toDouble()).toInt())

            for (pageIndex in 0 until totalPages) {
                if (cancellationSignal.isCanceled) {
                    callback.onWriteCancelled()
                    pdfDocument.close()
                    return
                }
                val page = pdfDocument.startPage(pageIndex)
                canvasView.drawForPrint(page.canvas, commands, scale, pageIndex * pageHeightDp)
                pdfDocument.finishPage(page)
            }

            FileOutputStream(destination.fileDescriptor).use { pdfDocument.writeTo(it) }
            callback.onWriteFinished(arrayOf(PageRange.ALL_PAGES))
        } catch (e: Exception) {
            callback.onWriteFailed(e.message)
        } finally {
            pdfDocument.close()
        }
    }
}
