<?php

/**
 * ImageUploadBehavior class file.
 * 
 * @author Tomoki Morita <tmsongbooks215@gmail.com>
 * @link https://github.com/jamband
 */
class ImageUploadBehavior extends CActiveRecordBehavior
{
	/**
	 * @var string the file upload column name
	 */
	public $uploadColumn = 'image';

	/**
	 * @var boolean whether creates the thumbnail
	 */
	public $createThumb = false;

	/**
	 * @var integer purge limit (second)
	 */
	public $purgeLimit = 600;

	/**
	 * @var string empty image
	 */
	public $emptyImage = 'empty.jpg';

	/**
	 * @var integer width
	 */
	public $width = 200;

	/**
	 * @var integer height
	 */
	public $height = 140;

	/**
	 * @var integer quality
	 */
	public $quality = 90;

	private $_dir;
	private $_thumbDir;
	private $_tmpDir = 'images/tmp/';
	private $_tmpThumbDir = 'images/tmp/thumb/';
	private $_oldImage;

	/**
	 * @see CBehavior::attach()
	 */
	public function attach($owner)
	{
		parent::attach($owner);

		$this->_dir = 'images/'.$this->getOwner()->tableName().'/';
		$this->_thumbDir = 'images/'.$this->getOwner()->tableName().'/thumb/';
	}

	/**
	 * @see CModelBehavior::afterValidate()
	 */
	public function afterValidate($event)
	{
		if ($this->owner->scenario !== 'update')
		{
			$this->purge();

			if (!$this->owner->hasErrors())
			{
				$this->upload();
				$this->resize();
			}
		}
	}

	/**
	 * @see CActiveRecordBehavior::afterSave()
	 */
	public function afterSave($event)
	{
		if ($this->owner->scenario !== 'update')
		{
			if ($this->_oldImage)
				$this->delete();

			$this->move();
		}
	}

	/**
	 * @see CActiveRecordBehavior::afterDelete()
	 */
	public function afterDelete($event)
	{
		$this->delete();
	}

	/**
	 * @see CActiveRecordBehavior::afterFind()
	 */
	public function afterFind($event)
	{
		$this->_oldImage = $this->owner->image;
	}

	/**
	 * Purges the files.
	 */
	protected function purge()
	{
		foreach (array($this->_tmpDir, $this->_tmpThumbDir) as $path)
		{
			$files = Yii::app()->file->set($path.'*');
			foreach (glob($files->realPath) as $file)
			{
				if (time() - filemtime($file) > $this->purgeLimit)
					@unlink($file);
			}
		}
	}

	/**
	 * Uploads a new file.
	 */
	protected function upload()
	{
		$file = Yii::app()->file->set(ucfirst($this->owner->tableName()).'['.$this->uploadColumn.']');

		if (!$file->isUploaded)
			$file = Yii::app()->file->set('images/'.$this->emptyImage);

		$this->owner->image = md5(uniqid(rand(), true)).'.'.$file->extension;
		$file->copy($this->_tmpDir.$this->owner->image);
	}

	/**
	 * Resizes the file.
	 */
	protected function resize()
	{
		$file = Yii::app()->image->load($this->_tmpDir.$this->owner->image);
		$file->resize($this->width, $this->height)->quality($this->quality);

		if (!$this->createThumb)
			$file->save($this->_tmpDir.$this->owner->image);
		else
			$file->save($this->_tmpThumbDir.$this->owner->image);
	}

	/**
	 * Moves the upload file.
	 */
	protected function move()
	{
		$paths = array(
			$this->_tmpDir => $this->_dir,
			$this->_tmpThumbDir => $this->_thumbDir,
		);

		foreach ($paths as $tmpPath => $path)
		{
			$file = Yii::app()->file->set($tmpPath.$this->owner->image);
			if ($file->isFile)
				$file->rename($path.$this->owner->image);
		}
	}

	/**
	 * Deletes the upload file.
	 */
	protected function delete()
	{
		$image = $this->owner->image;

		if ($this->owner->scenario === 'change')
			$image = $this->_oldImage;

		foreach (array($this->_dir, $this->_thumbDir) as $path)
		{
			$file = Yii::app()->file->set($path.$image);
			if ($file->isFile)
				$file->delete();
		}
	}
}
