import 'buyer_navigation.dart';

import 'package:flutter/foundation.dart';
import 'package:image_picker/image_picker.dart';
import 'package:material_ui/material_ui.dart';
import 'package:video_player/video_player.dart';

import 'buyer_api.dart';
import 'buyer_records.dart';
import 'buyer_screens.dart';
import 'buyer_state.dart';
import 'design.dart';

class ChosenMedia {
  ChosenMedia(this.name, this.bytes, this.video);
  final String name;
  final Uint8List bytes;
  final bool video;
}

class AttachmentPicker extends StatefulWidget {
  const AttachmentPicker({
    super.key,
    required this.onChanged,
    this.disabled = false,
    this.singlePhoto = false,
    this.photoLimit = 2 * 1024 * 1024,
    this.videoLimit = 2 * 1024 * 1024,
  });
  final ValueChanged<List<ChosenMedia>> onChanged;
  final bool disabled, singlePhoto;
  final int photoLimit, videoLimit;
  @override
  State<AttachmentPicker> createState() => _AttachmentPickerState();
}

class _AttachmentPickerState extends State<AttachmentPicker> {
  final List<ChosenMedia> _files = [];
  bool _picking = false;
  Future<void> _pick(bool video) async {
    if (_picking) return;
    setState(() => _picking = true);
    try {
      final picker = ImagePicker();
      final XFile? file = video
          ? await picker.pickVideo(source: ImageSource.gallery)
          : await picker.pickImage(
              source: ImageSource.gallery,
              imageQuality: 90,
              requestFullMetadata: false,
            );
      if (file == null || !mounted) return;
      if (await file.length() >
          (video ? widget.videoLimit : widget.photoLimit)) {
        throw const ApiFailure(
          'Choose a smaller attachment. This file exceeds the server upload limit.',
        );
      }
      final bytes = await file.readAsBytes();
      if (bytes.length > (video ? widget.videoLimit : widget.photoLimit)) {
        throw ApiFailure(
          'Choose a smaller ${video ? 'video' : 'photo'} (limit ${((video ? widget.videoLimit : widget.photoLimit) / 1024 / 1024).toStringAsFixed(1)} MB).',
        );
      }
      if (!mounted) return;
      setState(() {
        _files.add(ChosenMedia(file.name, bytes, video));
        widget.onChanged(List.unmodifiable(_files));
      });
    } on ApiFailure catch (e) {
      if (mounted) buyerMessage(context, e.display);
    } catch (_) {
      if (mounted) {
        buyerMessage(
          context,
          'Could not open this attachment. Please choose another file.',
        );
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Wrap(
        spacing: 8,
        children: [
          for (var i = 0; i < _files.length; i++)
            InputChip(
              label: Text(_files[i].video ? 'Video' : 'Photo ${i + 1}'),
              avatar: Icon(
                _files[i].video
                    ? Icons.videocam_outlined
                    : Icons.image_outlined,
              ),
              onDeleted: widget.disabled
                  ? null
                  : () => setState(() {
                      _files.removeAt(i);
                      widget.onChanged(List.unmodifiable(_files));
                    }),
            ),
        ],
      ),
      Wrap(
        spacing: 8,
        children: [
          OutlinedButton.icon(
            onPressed:
                widget.disabled ||
                    _picking ||
                    _files.where((f) => !f.video).length >=
                        (widget.singlePhoto ? 1 : 3)
                ? null
                : () => _pick(false),
            icon: const Icon(Icons.photo_outlined),
            label: const Text('Add photo'),
          ),
          if (!widget.singlePhoto)
            OutlinedButton.icon(
              onPressed:
                  widget.disabled || _picking || _files.any((f) => f.video)
                  ? null
                  : () => _pick(true),
              icon: const Icon(Icons.videocam_outlined),
              label: const Text('Add video'),
            ),
        ],
      ),
    ],
  );
}

class ProductReviewsScreen extends StatelessWidget {
  const ProductReviewsScreen({super.key, required this.product});
  final String product;
  @override
  Widget build(BuildContext context) => BuyerRecords(
    title: 'Product reviews',
    path: 'products/$product/reviews',
    public: true,
    empty: 'No reviews yet. Reviews appear after buyers complete their deliveries.',
    record: (context, review) => Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${review['author']} · ${review['rating']} / 5',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            Text(
              localTime(review['created_at']),
              style: const TextStyle(color: muted, fontSize: 12),
            ),
            if (review['feedback'] != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(review['feedback'] as String),
              ),
            Wrap(
              spacing: 8,
              children: [
                for (final media in objects(review['media']))
                  OutlinedButton.icon(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => ReviewMediaScreen(media: media),
                      ),
                    ),
                    icon: Icon(
                      (media['mime_type'] as String).startsWith('video/')
                          ? Icons.play_circle_outline
                          : Icons.image_outlined,
                    ),
                    label: Text(
                      (media['mime_type'] as String).startsWith('video/')
                          ? 'Video'
                          : 'Photo',
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class ReviewMediaScreen extends StatefulWidget {
  const ReviewMediaScreen({super.key, required this.media});
  final Json media;
  @override
  State<ReviewMediaScreen> createState() => _ReviewMediaScreenState();
}

class _ReviewMediaScreenState extends State<ReviewMediaScreen> {
  VideoPlayerController? _video;
  bool _started = false;
  String? _error;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    if ((widget.media['mime_type'] as String).startsWith('video/')) {
      final url = BuyerScope.of(context).api
          .uri('review-media/${widget.media['id']}');
      if (!kDebugMode && url.scheme != 'https') {
        _error = 'A secure media server is required.';
        return;
      }
      final controller = VideoPlayerController.networkUrl(url);
      _video = controller;
      controller
          .initialize()
          .then((_) {
            if (mounted) setState(() {});
          })
          .catchError((Object _) {
            if (mounted) setState(() => _error = 'This video is unavailable.');
          });
    }
  }

  @override
  void dispose() {
    _video?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final video = _video;
    final api = BuyerScope.of(context).api;
    return Scaffold(
      appBar: BuyerAppBar(title: const Text('Review attachment')),
      body: _error != null
          ? Center(child: Text(_error!))
          : video == null
          ? Center(
              child: InteractiveViewer(
                child: Image.network(
                  api.uri('review-media/${widget.media['id']}').toString(),
                  errorBuilder: (_, _, _) =>
                      const Text('This photo is unavailable.'),
                ),
              ),
            )
          : !video.value.isInitialized
          ? const Center(child: CircularProgressIndicator())
          : Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  AspectRatio(
                    aspectRatio: video.value.aspectRatio,
                    child: VideoPlayer(video),
                  ),
                  IconButton(
                    tooltip: video.value.isPlaying
                        ? 'Pause video'
                        : 'Play video',
                    onPressed: () => setState(() {
                      video.value.isPlaying ? video.pause() : video.play();
                    }),
                    icon: Icon(
                      video.value.isPlaying ? Icons.pause : Icons.play_arrow,
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}

class FeedbackScreen extends StatefulWidget {
  const FeedbackScreen({super.key, required this.shipment});
  final String shipment;
  @override
  State<FeedbackScreen> createState() => _FeedbackScreenState();
}

class _FeedbackScreenState extends State<FeedbackScreen> {
  Json? _data;
  String? _error, _owner;
  bool _busy = false, _uncertain = false;
  final _form = GlobalKey<FormState>();
  final Map<String, int> _ratings = {};
  final Map<String, String> _comments = {};
  final Map<String, bool> _anonymous = {};
  final Map<String, List<ChosenMedia>> _attachments = {};
  final Set<String> _tags = {};
  String _riderFeedback = '';
  final String _key = requestId();
  Map<String, String>? _pendingFields;
  List<BuyerUpload>? _pendingFiles;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final owner = BuyerScope.of(context).user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _data = null;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && owner != null) _load();
      });
    }
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await BuyerScope.of(context).api
          .data('GET', 'buyer/shipments/${widget.shipment}/feedback');
      if (mounted) setState(() => _data = result);
    } on ApiFailure catch (e) {
      if (mounted) setState(() => _error = e.display);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _submit() async {
    if (!_uncertain && !_form.currentState!.validate()) return;
    final buyer = BuyerScope.of(context);
    if (!_uncertain) {
      final fields = <String, String>{};
      final uploads = <BuyerUpload>[];
      for (final item in objects(
        _data!['items'],
      ).where((i) => i['review'] == null)) {
        final id = item['id'] as String;
        fields['reviews[$id][rating]'] = '${_ratings[id]}';
        fields['reviews[$id][feedback]'] = _comments[id] ?? '';
        fields['reviews[$id][is_anonymous]'] = _anonymous[id] == true
            ? '1'
            : '0';
        for (var i = 0; i < (_attachments[id]?.length ?? 0); i++) {
          final file = _attachments[id]![i];
          uploads.add(
            BuyerUpload('reviews[$id][media][$i]', file.name, file.bytes),
          );
        }
      }
      for (final field in ['seller_service_rating', 'delivery_speed_rating']) {
        if (_data![field] == null) fields[field] = '${_ratings[field]}';
      }
      if (_data!['needs_rider_rating'] == true) {
        fields['rider_rating'] = '${_ratings['rider_rating']}';
        fields['rider_feedback'] = _riderFeedback;
        for (var i = 0; i < _tags.length; i++) {
          fields['rider_tags[$i]'] = _tags.elementAt(i);
        }
      }
      final limit =
          (_data!['media_limits'] as Map?)?['total_bytes'] as int? ??
          7 * 1024 * 1024;
      if (uploads.fold<int>(0, (s, f) => s + f.bytes.length) > limit) {
        setState(
          () => _error = 'Attachments exceed this server’s total upload limit. Choose fewer or smaller files.',
        );
        return;
      }
      _pendingFields = fields;
      _pendingFiles = uploads;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await buyer.api.multipart(
        'buyer/shipments/${widget.shipment}/feedback',
        _pendingFields!,
        _pendingFiles!,
        _key,
      );
      if (mounted) {
        buyerMessage(context, 'Your feedback has been saved.');
        Navigator.pop(context);
      }
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          _uncertain = e.uncertain;
          _error = e.uncertain
              ? 'The result is unclear. Retry the same feedback to check it.'
              : e.display;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _rating(String key, String label) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 10),
    child: DropdownButtonFormField<int>(
      decoration: InputDecoration(labelText: label),
      items: [
        for (var i = 1; i <= 5; i++)
          DropdownMenuItem(
            value: i,
            child: Text('$i ${i == 1 ? 'star' : 'stars'}'),
          ),
      ],
      onChanged: _busy || _uncertain ? null : (v) => _ratings[key] = v!,
      validator: (v) => v == null ? 'Select a rating.' : null,
    ),
  );
  @override
  Widget build(BuildContext context) {
    final data = _data;
    return Scaffold(
      appBar: BuyerAppBar(title: const Text('Product and delivery feedback')),
      body: !BuyerScope.of(context).signedIn
          ? const SignInRequired()
          : Form(
              key: _form,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  if (_busy) const LinearProgressIndicator(),
                  if (_error != null) Notice(_error!),
                  if (data == null && !_busy)
                    TextButton(onPressed: _load, child: const Text('Retry')),
                  if (data != null) ...[
                    for (final item in objects(data['items']))
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item['name'] as String,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              Text('SKU: ${item['sku'] ?? '—'}'),
                              if (item['review'] != null)
                                Text(
                                  'Already reviewed · ${object(item['review'])['rating']} / 5',
                                )
                              else ...[
                                _rating(item['id'] as String, 'Product rating'),
                                TextFormField(
                                  enabled: !_busy && !_uncertain,
                                  maxLength: 2000,
                                  maxLines: 3,
                                  decoration: const InputDecoration(
                                    labelText: 'Comments (optional)',
                                  ),
                                  onChanged: (v) =>
                                      _comments[item['id'] as String] = v,
                                ),
                                CheckboxListTile(
                                  contentPadding: EdgeInsets.zero,
                                  title: const Text('Review anonymously'),
                                  value: _anonymous[item['id']] ?? false,
                                  onChanged: _busy || _uncertain
                                      ? null
                                      : (v) => setState(
                                          () =>
                                              _anonymous[item['id'] as String] =
                                                  v ?? false,
                                        ),
                                ),
                                AttachmentPicker(
                                  key: ValueKey(item['id']),
                                  disabled: _busy || _uncertain,
                                  photoLimit:
                                      (data['media_limits']
                                              as Map?)?['photo_bytes']
                                          as int? ??
                                      2 * 1024 * 1024,
                                  videoLimit:
                                      (data['media_limits']
                                              as Map?)?['video_bytes']
                                          as int? ??
                                      2 * 1024 * 1024,
                                  onChanged: (files) =>
                                      _attachments[item['id'] as String] =
                                          files,
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                    if (data['seller_service_rating'] == null)
                      _rating('seller_service_rating', 'Seller service'),
                    if (data['delivery_speed_rating'] == null)
                      _rating('delivery_speed_rating', 'Delivery speed'),
                    if (data['needs_rider_rating'] == true) ...[
                      _rating('rider_rating', 'Rider rating'),
                      TextFormField(
                        enabled: !_busy && !_uncertain,
                        maxLength: 1000,
                        decoration: const InputDecoration(
                          labelText: 'Rider feedback (optional)',
                        ),
                        onChanged: (v) => _riderFeedback = v,
                      ),
                      Wrap(
                        spacing: 8,
                        children: [
                          for (final tag in data['rider_tags'] as List)
                            FilterChip(
                              label: Text('$tag'),
                              selected: _tags.contains(tag),
                              onSelected: _busy || _uncertain
                                  ? null
                                  : (v) => setState(() {
                                      v
                                          ? _tags.add('$tag')
                                          : _tags.remove('$tag');
                                    }),
                            ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: Text(
                        _uncertain
                            ? 'Check feedback result'
                            : 'Submit feedback',
                      ),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}
